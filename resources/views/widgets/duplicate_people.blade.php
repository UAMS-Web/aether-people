@php use function Statamic\trans as __; @endphp

{{-- Emulates Statamic v6 UI Panel + Header (Panel.vue, Header.vue) --}}
{{-- `v-pre` on the root (UAMS-Web/uams-statamic-archive#2572): the dashboard compiles widget HTML as a Vue template, and the
     entry titles below are editor text, so a `{{ ... }}` in one would otherwise be evaluated. --}}
<div
    v-pre
    class="@container/panel relative bg-gray-150 dark:bg-gray-950/35 dark:inset-shadow-2xs dark:inset-shadow-black w-full rounded-2xl max-[600px]:p-1.25 p-1.75 focus-none starting-style-transition h-full flex flex-col overflow-hidden [&:has(>[data-ui-panel-header])]:pt-0"
    data-ui-panel
>
    <header class="px-4.5 py-3 flex items-center justify-between" data-ui-panel-header>
        <div data-ui-heading class="font-medium antialiased flex items-center gap-2 text-sm tracking-tight text-gray-700 dark:text-white">
            @cp_svg('icons/users', 'size-4 shrink-0 text-gray-500 dark:text-gray-500')
            <span>{{ __($title) }}</span>
        </div>
    </header>
    <div class="flex-1 overflow-auto min-h-0">
        @if (empty($clusters))
            <p class="px-4.5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ __('No likely-duplicate people detected.') }}</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="group rounded-none">{{ __('Confidence') }}</th>
                        <th class="group rounded-none">{{ __('Candidate entries') }}</th>
                        <th class="group rounded-none">{{ __('Matched on') }}</th>
                        <th class="group rounded-none"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clusters as $cluster)
                        <tr class="align-top">
                            <td>
                                @php
                                    $badge = match ($cluster['confidence']) {
                                        'high' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
                                        'medium' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                                        default => 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">
                                    {{ __(ucfirst($cluster['confidence'])) }}
                                </span>
                            </td>
                            <td>
                                <ul class="space-y-0.5">
                                    @foreach ($cluster['members'] as $member)
                                        <li>
                                            <a href="{{ $member['edit_url'] }}" class="title-index-field inline-flex items-center">{{ $member['title'] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td><span class="text-gray-600 dark:text-gray-400">{{ $cluster['signals'] }}</span></td>
                            <td>
                                <a href="{{ $cluster['merge_url'] }}" class="text-blue dark:text-blue-400 hover:underline whitespace-nowrap">
                                    {{ __('Review & merge') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
