<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ValidateAttributeWithoutCallComponent extends Component
{
    #[Validate('required')]
    public string $title = '';

    public function save(): void
    {
    }
}

class RuleAttributeWithoutCallComponent extends Component
{
    #[Rule('required')]
    public string $title = '';

    public function save(): void
    {
    }
}

class RulesMethodWithoutCallComponent extends Component
{
    public string $title = '';

    public function rules(): array
    {
        return ['title' => 'required'];
    }

    public function save(): void
    {
    }
}

class RulesPropertyWithoutCallComponent extends Component
{
    protected array $rules = ['title' => 'required'];

    public function save(): void
    {
    }
}

trait DeclaresValidatedTitle
{
    #[Validate('required')]
    public string $title = '';
}

class TraitRulesWithoutCallComponent extends Component
{
    use DeclaresValidatedTitle;

    public function save(): void
    {
    }
}
