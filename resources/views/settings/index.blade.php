@extends('layouts.app')

@section('title', 'SMP WordPress TTS Settings')

@section('content')
@php
    $providerLabels = collect($providers)->mapWithKeys(fn ($provider, $id) => [$id => $provider['label']])->all();
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">SMP WordPress TTS Settings</h1>
            <p class="mt-1 text-sm text-gray-500">Central provider credentials, validation, and WordPress plugin defaults for Scale My Publication text-to-speech sites.</p>
        </div>
        <a href="{{ route('smp-wordpress-tts.dashboard') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            Open Dashboard
        </a>
    </div>

    @if(session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ session('status') }}</div>
    @endif


    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm" x-data="smpTtsProviderKeyring()">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">UnrealSpeech API Keys</h2>
                <p class="mt-1 text-sm text-gray-500">Named repeater-style keyring. Add multiple keys, test each one, and choose the active/default key used by the central Publish Scale API.</p>
            </div>
            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700" x-text="keys.length + " key(s)""></span>
        </div>
        <div class="space-y-3">
            <template x-for="key in keys" :key="key.id">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="text-sm font-bold text-gray-900" x-text="key.name"></div>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500"><span class="font-mono" x-text="key.masked_key"></span><span x-show="key.is_active" class="rounded-full bg-green-100 px-2 py-0.5 font-semibold text-green-700">Active</span></div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700" @click="testKey(key.id)">Test Key</button>
                            <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700" @click="setActive(key.id)" :disabled="key.is_active">Set Active</button>
                        </div>
                    </div>
                </div>
            </template>
            <div x-show="!keys.length" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-500">No UnrealSpeech keys stored yet.</div>
        </div>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <label class="block"><span class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">Key Name</span><input type="text" x-model="newName" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="michael@mike-ro-tech.com"></label>
            <label class="block"><span class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">API Key</span><input type="password" x-model="newKey" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono" placeholder="Paste UnrealSpeech key"></label>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" x-model="makeActive" class="rounded border-gray-300 text-blue-600"><span>Make active after adding</span></label>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white" @click="addKey()">Add Key</button>
            <button type="button" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700" @click="testDraft()">Test Unsaved Key</button>
        </div>
        <div x-show="message" class="mt-4 rounded-lg border px-4 py-3 text-sm" :class="ok ? "border-green-200 bg-green-50 text-green-800" : "border-red-200 bg-red-50 text-red-800"" x-text="message"></div>
    </section>

    <form method="post" action="{{ route('smp-wordpress-tts.settings.save') }}" class="space-y-6">
        @csrf

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">WordPress Defaults</h2>
                    <p class="mt-1 text-sm text-gray-500">These settings are pushed into the WordPress plugin when a target install is selected from the dashboard.</p>
                </div>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Defaults</button>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                <label class="block">
                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">Default Provider</span>
                    <select name="default_provider" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach($providerLabels as $id => $label)
                            <option value="{{ $id }}" @selected(($settings['default_provider'] ?? '') === $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">Default Profile</span>
                    <select name="default_profile" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach(($settings['profiles'] ?? []) as $profileId => $profile)
                            <option value="{{ $profileId }}" @selected(($settings['default_profile'] ?? '') === $profileId)>{{ $profile['label'] ?? ucfirst($profileId) }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">Max Characters</span>
                    <input name="max_characters" type="number" min="500" step="500" value="{{ $settings['max_characters'] ?? 20000 }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                </label>

                <label class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                    <input type="checkbox" name="auto_insert_player" value="1" @checked(!empty($settings['auto_insert_player'])) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-medium text-gray-700">Auto-insert player</span>
                </label>

                <label class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                    <input type="checkbox" name="include_title" value="1" @checked(!empty($settings['include_title'])) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-medium text-gray-700">Include title</span>
                </label>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-gray-900">Provider Credentials</h2>
                <p class="mt-1 text-sm text-gray-500">Secret values are saved with the Hexa Core CredentialService. Use Change, Save, and Test on each encrypted field.</p>
            </div>

            <div class="space-y-5">
                @foreach($providers as $providerId => $provider)
                    <article class="rounded-lg border border-gray-200">
                        <div class="border-b border-gray-200 bg-gray-50 px-5 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900">{{ $provider['label'] }}</h3>
                                    <p class="mt-1 text-sm text-gray-500">{{ $provider['summary'] }}</p>
                                </div>
                                <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-500 ring-1 ring-gray-200">{{ $providerId }}</span>
                            </div>
                        </div>

                        <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(340px,.8fr)]">
                            <div class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    @foreach($provider['fields'] as $fieldId => $field)
                                        @if(!empty($field['secret']))
                                            <div class="md:col-span-2">
                                                <x-hexa-credential-field
                                                    :slug="$credentialSlug"
                                                    :key-name="str_replace('-', '_', $providerId . '_' . $fieldId)"
                                                    :label="$provider['label'] . ' ' . $field['label']"
                                                    :test-url="route('smp-wordpress-tts.settings.test', ['provider' => $providerId])"
                                                    :help="$field['help'] ?? ''"
                                                />
                                            </div>
                                        @else
                                            <label class="block">
                                                <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">{{ $field['label'] }}</span>
                                                @if(($field['type'] ?? 'text') === 'select')
                                                    <select name="providers[{{ $providerId }}][{{ $fieldId }}]" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                                                        @foreach(($field['options'] ?? []) as $optionValue => $optionLabel)
                                                            <option value="{{ $optionValue }}" @selected(($settings['providers'][$providerId][$fieldId] ?? ($field['default'] ?? '')) === (string) $optionValue)>{{ $optionLabel }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input
                                                        name="providers[{{ $providerId }}][{{ $fieldId }}]"
                                                        type="{{ $field['type'] ?? 'text' }}"
                                                        value="{{ $settings['providers'][$providerId][$fieldId] ?? ($field['default'] ?? '') }}"
                                                        placeholder="{{ $field['default'] ?? '' }}"
                                                        @if(($field['type'] ?? '') === 'number') step="0.05" @endif
                                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                                                    >
                                                @endif
                                                @if(!empty($field['help']))
                                                    <span class="mt-1 block text-xs text-gray-400">{{ $field['help'] }}</span>
                                                @endif
                                            </label>
                                        @endif
                                    @endforeach
                                </div>
                            </div>

                            <aside class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                                <h4 class="text-sm font-semibold text-blue-950">Detailed Setup Steps</h4>
                                <p class="mt-1 text-xs font-medium text-blue-800">Follow these in order. Source links below open in a new tab.</p>
                                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm leading-6 text-blue-950">
                                    @foreach(($provider['instructions'] ?? []) as $instruction)
                                        <li>{{ $instruction }}</li>
                                    @endforeach
                                </ol>
                                @if(!empty($provider['docs']))
                                    <h5 class="mt-5 text-xs font-black uppercase tracking-wide text-blue-900">Official Links</h5>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($provider['docs'] as $doc)
                                            <a href="{{ $doc['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-200 hover:bg-blue-100">
                                                {{ $doc['label'] }} <span aria-hidden="true">↗</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </aside>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Save Non-Secret Settings</button>
        </div>
    </form>
</div>
@endsection

@push("scripts")
<script>
function smpTtsProviderKeyring() {
    return {
        keys: @js($unrealKeys),
        newName: "",
        newKey: "",
        makeActive: true,
        message: "",
        ok: true,
        urls: {
            add: @js(route("smp-wordpress-tts.provider-keys.add", ["provider" => "unrealspeech"])),
            test: @js(route("smp-wordpress-tts.provider-keys.test", ["provider" => "unrealspeech"])),
            active: @js(route("smp-wordpress-tts.provider-keys.active", ["provider" => "unrealspeech"])),
        },
        headers() {
            return {"Accept": "application/json", "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]")?.content || ""};
        },
        async read(response) {
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.success === false) throw new Error(data.message || "Request failed");
            return data;
        },
        async addKey() {
            try {
                const data = await this.read(await fetch(this.urls.add, {method: "POST", headers: this.headers(), body: JSON.stringify({name: this.newName, api_key: this.newKey, make_active: this.makeActive})}));
                this.keys = data.keys || this.keys;
                this.newKey = "";
                this.message = "Key added.";
                this.ok = true;
            } catch (error) { this.message = error.message; this.ok = false; }
        },
        async testDraft() {
            try {
                const data = await this.read(await fetch(this.urls.test, {method: "POST", headers: this.headers(), body: JSON.stringify({api_key: this.newKey})}));
                this.message = data.message || "Key tested.";
                this.ok = !!data.success;
            } catch (error) { this.message = error.message; this.ok = false; }
        },
        async testKey(id) {
            try {
                const data = await this.read(await fetch(this.urls.test, {method: "POST", headers: this.headers(), body: JSON.stringify({key_id: id})}));
                this.message = data.message || "Key tested.";
                this.ok = !!data.success;
            } catch (error) { this.message = error.message; this.ok = false; }
        },
        async setActive(id) {
            try {
                const data = await this.read(await fetch(this.urls.active, {method: "POST", headers: this.headers(), body: JSON.stringify({key_id: id})}));
                this.keys = data.keys || this.keys;
                this.message = data.message || "Active key updated.";
                this.ok = true;
            } catch (error) { this.message = error.message; this.ok = false; }
        },
    };
}
</script>
@endpush
