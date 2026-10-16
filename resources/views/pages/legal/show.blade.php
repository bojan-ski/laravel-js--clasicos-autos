<x-layouts.app :title="$document->title" :seo="$seo">
    <x-ui.back-link :href="$backUrl" :label="$document->kicker" />
    <x-legal.document :document="$document" />
</x-layouts.app>
