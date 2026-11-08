<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassMethodsNode;
use PHPStan\Reflection\ClassReflection;

/**
 * Finds a component's Blade source, the same way Livewire does: the rest of the file
 * for a single-file component, the sibling template for a multi-file one, whatever
 * render() names, and otherwise `livewire/<kebab-name>.blade.php`.
 */
final class ComponentViews
{
    /**
     * @param  list<string>  $viewPaths
     */
    public function __construct(
        private readonly array $viewPaths,
    ) {}

    public function bindingsOf(ClassMethodsNode $node, ComponentMethods $methods, Scope $scope): ViewBindings
    {
        $class = $node->getClassReflection();
        $file = $scope->getFile();
        $render = $methods->find('render');

        if (str_ends_with($file, '.blade.php')) {
            $bindings = $this->read($file, $scope);
        } elseif ($render !== null) {
            $bindings = $this->fromRender($render, $scope);
        } elseif ($class->isAnonymous()) {
            $bindings = $this->read(substr($file, 0, -\strlen('.php')) . '.blade.php', $scope);
        } else {
            $bindings = $this->fromView('livewire.' . $this->conventionalName($class), $scope);
        }

        return $this->followIncludes($bindings, $scope);
    }

    private function fromRender(ClassMethod $render, Scope $scope): ViewBindings
    {
        $bindings = ViewBindings::none();
        $found = false;

        foreach (ComponentMethods::findIn($render, static fn (Node $node): bool => true) as $node) {
            if ($node instanceof Return_ && $node->expr !== null) {
                $inline = $this->inlineTemplate($node->expr);

                if ($inline !== null) {
                    $bindings = $bindings->merge(ViewBindings::fromBlade($inline));
                    $found = true;
                }
            }

            if (! $this->isViewCall($node)) {
                continue;
            }

            /** @var FuncCall|MethodCall|StaticCall $node */
            $name = $node->isFirstClassCallable() ? null : ($node->getArgs()[0]->value ?? null);

            if ($name instanceof String_) {
                $bindings = $bindings->merge($this->fromView($name->value, $scope));
                $found = true;
            } elseif ($name !== null) {
                $bindings = $bindings->incomplete();
            }
        }

        return $found ? $bindings : $bindings->incomplete();
    }

    private function inlineTemplate(Node $expression): ?string
    {
        if ($expression instanceof String_) {
            return $expression->value;
        }

        if ($expression instanceof InterpolatedString) {
            $source = '';

            foreach ($expression->parts as $part) {
                $source .= $part instanceof Node\InterpolatedStringPart ? $part->value : ' ';
            }

            return $source;
        }

        return null;
    }

    /**
     * `view('x')`, `view()->make('x')` and `View::make('x')`.
     */
    private function isViewCall(Node $node): bool
    {
        if ($node instanceof FuncCall) {
            return $node->name instanceof Name && $node->name->toLowerString() === 'view';
        }

        if ($node instanceof MethodCall) {
            return $node->name instanceof Identifier
                && $node->name->toLowerString() === 'make'
                && $node->var instanceof FuncCall
                && $node->var->name instanceof Name
                && $node->var->name->toLowerString() === 'view';
        }

        return $node instanceof StaticCall
            && $node->class instanceof Name
            && $node->class->getLast() === 'View'
            && $node->name instanceof Identifier
            && $node->name->toLowerString() === 'make';
    }

    private function followIncludes(ViewBindings $bindings, Scope $scope): ViewBindings
    {
        $seen = [];
        $queue = $bindings->includes;

        while ($queue !== []) {
            $view = array_shift($queue);
            if (isset($seen[$view])) {
                continue;
            }
            $seen[$view] = true;

            if (isset($bindings->optionalIncludes[$view]) && ! str_contains($view, '::') && $this->locate($view, $scope) === null) {
                continue;
            }

            $included = $this->fromView($view, $scope);
            $bindings = $bindings->merge($included);
            array_push($queue, ...$included->includes);
        }

        return $bindings;
    }

    private function fromView(string $view, Scope $scope): ViewBindings
    {
        // A namespaced view, such as `pages::post.show`, could live anywhere.
        if (str_contains($view, '::')) {
            return ViewBindings::none()->incomplete();
        }

        $path = $this->locate($view, $scope);

        return $path === null ? ViewBindings::none()->incomplete() : $this->read($path, $scope);
    }

    private function locate(string $view, Scope $scope): ?string
    {
        $relative = str_replace('.', '/', $view) . '.blade.php';

        foreach ($this->viewPaths as $root) {
            $path = rtrim($root, '/') . '/' . $relative;

            if (is_file($path)) {
                return $path;
            }

            $this->track($path, $scope);
        }

        return null;
    }

    private function read(string $path, Scope $scope): ViewBindings
    {
        $this->track($path, $scope);

        $blade = is_file($path) ? file_get_contents($path) : false;

        return $blade === false
            ? ViewBindings::none()->incomplete()
            : ViewBindings::fromBlade($blade);
    }

    /**
     * Without this, PHPStan's result cache wouldn't know a change to a view affects
     * the component. Only available on PHPStan 2.3 or later.
     */
    private function track(string $path, Scope $scope): void
    {
        if ($scope instanceof DependencyTracker) {
            $scope->trackFileDependency($path);
        }
    }

    /**
     * `App\Livewire\Admin\EditPost` renders `livewire/admin/edit-post.blade.php`. The
     * namespace Livewire strips is configurable, so anything up to `Livewire\` goes.
     */
    private function conventionalName(ClassReflection $class): string
    {
        $name = $class->getName();
        $position = strrpos($name, '\\Livewire\\');

        $segments = $position === false
            ? [substr((string) strrchr('\\' . $name, '\\'), 1)]
            : explode('\\', substr($name, $position + \strlen('\\Livewire\\')));

        return implode('.', array_map(
            static fn (string $segment): string => strtolower((string) preg_replace('/(.)(?=[A-Z])/u', '$1-', $segment)),
            $segments,
        ));
    }
}
