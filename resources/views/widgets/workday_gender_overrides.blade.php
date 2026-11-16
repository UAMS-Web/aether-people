@php use function Statamic\trans as __; @endphp

{{-- Emulates Statamic v6 UI Panel + Header (Panel.vue, Header.vue), as duplicate_people does --}}
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
        @if (empty($rows))
            <p class="px-4.5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ __('No gender overrides to review.') }}</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="group rounded-none">{{ __('Person') }}</th>
                        <th class="group rounded-none">{{ __('Gender') }}</th>
                        <th class="group rounded-none">{{ __('In Workday') }}</th>
                        <th class="group rounded-none"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="align-top" data-workday-gender-row="{{ $row['id'] }}">
                            <td>
                                <a href="{{ $row['edit_url'] }}" class="title-index-field inline-flex items-center">{{ $row['title'] }}</a>
                            </td>
                            <td>{{ $row['gender'] }}</td>
                            <td><span class="text-gray-600 dark:text-gray-400">{{ $row['workday_gender'] }}</span></td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ $row['confirm_url'] }}">
                                        @csrf
                                        <button type="submit" class="text-blue dark:text-blue-400 hover:underline whitespace-nowrap">{{ __('Keep override') }}</button>
                                    </form>
                                    <form method="POST" action="{{ $row['take_url'] }}">
                                        @csrf
                                        <button type="submit" class="text-blue dark:text-blue-400 hover:underline whitespace-nowrap">{{ __('Use Workday value') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
