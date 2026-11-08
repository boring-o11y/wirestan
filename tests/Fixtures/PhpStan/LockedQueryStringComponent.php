<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

class LockedQueryStringComponent extends Component
{
    public int $page = 1;

    public string $sort = '';

    public array $filters = [];

    public int $bookingId = 0;

    protected $queryString = ['page', 'sort' => ['except' => ''], 'filters.type' => ['as' => 'type']];
}

class LockedQueryStringMethodComponent extends Component
{
    public string $search = '';

    public int $bookingId = 0;

    protected function queryString(): array
    {
        return ['search' => ['as' => 'q']];
    }
}
