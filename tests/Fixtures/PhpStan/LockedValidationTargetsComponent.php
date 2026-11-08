<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

class LockedValidationTargetsComponent extends Component
{
    public string $fromRulesMethod = '';

    public array $nestedInRules = [];

    public string $fromRulesProperty = '';

    public string $fromInlineValidate = '';

    public string $fromValidateOnly = '';

    public int $bookingId = 0;

    protected array $rules = ['fromRulesProperty' => 'required'];

    protected function rules(): array
    {
        return [
            'fromRulesMethod' => 'required',
            'nestedInRules.*.name' => 'required',
        ];
    }

    public function save(): void
    {
        $this->validate(['fromInlineValidate' => 'required']);
        $this->validateOnly('fromValidateOnly');
    }
}
