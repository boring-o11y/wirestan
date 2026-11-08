<div>
    <input wire:model="inView">
    @include('custom.partial', ['a' => 1])
    @includeWhen($show, 'custom.conditional')
    @includeIf('custom.rendered')
    @includeUnless($hide, 'custom.unless')
    @includeFirst(['custom.missing-first', 'custom.first'])
    @each('custom.each', $rows, 'row')
</div>
