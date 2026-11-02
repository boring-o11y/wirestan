<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CallsValidateComponent extends Component
{
    #[Validate('required')]
    public string $title = '';

    public function save(): void
    {
        $this->validate();
    }
}

class CallsValidateOnlyComponent extends Component
{
    public string $title = '';

    public function rules(): array
    {
        return ['title' => 'required'];
    }

    public function updatedTitle(): void
    {
        $this->validateOnly('title');
    }

    public function save(): void
    {
    }
}

class CallsValidateOnFormComponent extends Component
{
    #[Validate('required')]
    public string $title = '';

    public function save(): void
    {
        $this->form->validate();
    }
}

class CallsValidatorFacadeComponent extends Component
{
    #[Validate('required')]
    public string $title = '';

    public function save(): void
    {
        Validator::validate(['title' => $this->title], ['title' => 'required']);
    }
}

trait SavesWithValidation
{
    public function save(): void
    {
        $this->validate();
    }
}

class ValidatesInTraitComponent extends Component
{
    use SavesWithValidation;

    #[Validate('required')]
    public string $title = '';
}

// Nothing the client can trigger, so there is nothing to enforce against.
class NoActionsComponent extends Component
{
    #[Validate('max:100')]
    public string $search = '';

    public function rules(): array
    {
        return [];
    }

    public function updatedSearch(): void
    {
    }

    public function render(): mixed
    {
        return null;
    }
}

class NoRulesComponent extends Component
{
    public string $title = '';

    public function save(): void
    {
    }
}

// A subclass may call validate(), and its body isn't visible from here.
abstract class AbstractRulesComponent extends Component
{
    #[Validate('required')]
    public string $title = '';

    public function save(): void
    {
    }
}

// The custom base class may call validate(), and its body isn't visible from here.
class ChildOfCustomBaseComponent extends AbstractRulesComponent
{
    #[Validate('required')]
    public string $body = '';

    public function publish(): void
    {
    }
}

class PlainClassWithRules
{
    #[Validate('required')]
    public string $title = '';

    public function save(): void
    {
    }
}
