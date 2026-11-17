@props(['cards'])

<ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
    @foreach ($cards as $card)
        <li><x-catalog::title-card :card="$card" /></li>
    @endforeach
</ul>
