@extends('layouts.app')

@section('title', 'SMP WordPress TTS')

@section('content')
<div x-data="smpWordPressTtsDashboard()" class="max-w-7xl mx-auto space-y-6">
    <style>
        [x-cloak]{display:none!important}
        .tts-panel{border:1px solid #e5e7eb;background:#fff;border-radius:8px}
        .tts-panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;padding:14px 16px}
        .tts-panel-body{padding:16px}
        .tts-title{font-size:15px;font-weight:800;color:#111827}
        .tts-copy{font-size:12px;color:#6b7280;margin-top:2px}
        .tts-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:1px solid #d1d5db;border-radius:7px;background:#fff;padding:8px 11px;font-size:12px;font-weight:800;color:#374151;min-height:34px}
        .tts-btn:hover{background:#f9fafb}.tts-btn:disabled{opacity:.55;cursor:not-allowed}
        .tts-btn-primary{background:#2563eb;border-color:#2563eb;color:#fff}.tts-btn-primary:hover{background:#1d4ed8}
        .tts-btn-dark{background:#111827;border-color:#111827;color:#fff}.tts-btn-dark:hover{background:#1f2937}
        .tts-btn-warn{background:#fffbeb;border-color:#f59e0b;color:#92400e}.tts-btn-warn:hover{background:#fef3c7}
        .tts-input,.tts-select{width:100%;border:1px solid #d1d5db;border-radius:7px;padding:9px 10px;font-size:13px;color:#111827;background:#fff}
        .tts-label{display:block;font-size:10px;font-weight:900;text-transform:uppercase;color:#6b7280;margin-bottom:5px}
        .tts-row{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#fff}
        .tts-row + .tts-row{margin-top:8px}.tts-row:hover{border-color:#9ca3af}
        .tts-name{font-size:13px;font-weight:900;color:#111827}.tts-meta{font-size:11px;color:#6b7280;margin-top:2px;word-break:break-word}
        .tts-badge{display:inline-flex;align-items:center;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:800;background:#f3f4f6;color:#4b5563}
        .tts-ok{background:#dcfce7;color:#166534}.tts-warn{background:#fef3c7;color:#92400e}.tts-bad{background:#fee2e2;color:#991b1b}
        .tts-grid{display:grid;grid-template-columns:350px minmax(0,1fr);gap:14px;align-items:start}
        .tts-table{width:100%;border-collapse:separate;border-spacing:0 7px}
        .tts-table th{font-size:10px;text-transform:uppercase;color:#6b7280;text-align:left;padding:0 8px}
        .tts-table td{background:#fff;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;padding:9px 8px;font-size:12px;vertical-align:top}
        .tts-table td:first-child{border-left:1px solid #e5e7eb;border-radius:8px 0 0 8px}.tts-table td:last-child{border-right:1px solid #e5e7eb;border-radius:0 8px 8px 0}
        .tts-spin{width:14px;height:14px;border-radius:999px;border:2px solid rgba(255,255,255,.55);border-top-color:#fff;animation:tts-rot .75s linear infinite}
        .tts-spin-dark{border-color:rgba(75,85,99,.35);border-top-color:#4b5563}
        @keyframes tts-rot{to{transform:rotate(360deg)}}@media(max-width:1050px){.tts-grid{grid-template-columns:1fr}}
    </style>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">SMP WordPress Text-to-Speech</h1>
            <p class="mt-1 text-sm text-gray-500">Select WHM accounts, discover WP Toolkit installs, detect the plugin, compare it to GitHub, collect usage stats, and push central credentials.</p>
            <p class="mt-1 text-xs text-gray-400">Plugin: {{ $plugin['slug'] }} · GitHub: {{ $plugin['repo'] }}@{{ $plugin['ref'] }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('smp-wordpress-tts.settings') }}" class="tts-btn">Settings</a>
            <a href="https://github.com/mikeyperes/smp-wordpress-text-to-speech" target="_blank" rel="noopener noreferrer" class="tts-btn">GitHub ↗</a>
        </div>
    </div>

    <template x-if="message">
        <div class="rounded-lg border px-4 py-3 text-sm" :class="messageOk ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800'">
            <div class="flex items-center justify-between gap-4">
                <span x-text="message"></span>
                <button type="button" class="text-xs font-bold" @click="message=''">Dismiss</button>
            </div>
        </div>
    </template>

    <div class="grid gap-3 md:grid-cols-4">
        <div class="tts-panel"><div class="tts-panel-body"><div class="tts-copy">Accounts Selected</div><div class="text-2xl font-black text-gray-900" x-text="selectedAccounts.length"></div></div></div>
        <div class="tts-panel"><div class="tts-panel-body"><div class="tts-copy">WP Installs</div><div class="text-2xl font-black text-gray-900" x-text="installs.length"></div></div></div>
        <div class="tts-panel"><div class="tts-panel-body"><div class="tts-copy">Selected Installs</div><div class="text-2xl font-black text-gray-900" x-text="selectedInstallKeys.length"></div></div></div>
        <div class="tts-panel"><div class="tts-panel-body"><div class="tts-copy">Scanned</div><div class="text-2xl font-black text-gray-900" x-text="results.length"></div></div></div>
    </div>

    <section class="tts-panel" x-data="smpTtsRealtime()" x-init="init()">
        <div class="tts-panel-head">
            <div><div class="tts-title">Realtime Requests</div><div class="tts-copy">Polls every five seconds for active processing and recent request history. No page refresh required.</div></div>
            <button type="button" class="tts-btn" @click="load()">Refresh now</button>
        </div>
        <div class="tts-panel-body">
            <div class="grid gap-3 md:grid-cols-3 mb-4">
                <div class="tts-panel"><div class="tts-panel-body"><div class="tts-copy">Active now</div><div class="text-2xl font-black text-gray-900" x-text="active.length"></div></div></div>
                <div class="tts-panel"><div class="tts-panel-body"><div class="tts-copy">Recent requests</div><div class="text-2xl font-black text-gray-900" x-text="requests.length"></div></div></div>
                <div class="tts-panel"><div class="tts-panel-body"><div class="tts-copy">Last poll</div><div class="text-sm font-black text-gray-900" x-text="lastPoll || "Not yet""></div></div></div>
            </div>
            <div class="overflow-x-auto">
                <table class="tts-table">
                    <thead><tr><th>Status</th><th>Site / Article</th><th>User</th><th>Provider</th><th>Audio</th><th>Cost</th><th>Time EST</th></tr></thead>
                    <tbody>
                        <template x-for="row in requests" :key="row.id">
                            <tr>
                                <td><span class="tts-badge" :class="row.status === "complete" ? "tts-ok" : (row.status === "failed" ? "tts-bad" : "tts-warn")" x-text="row.status"></span><div class="tts-meta" x-text="row.message || row.id"></div></td>
                                <td><div class="tts-name" x-text="row.site || "unknown site""></div><a class="tts-meta" :href="row.article_url" target="_blank" rel="noopener" x-text="row.article_url || "no article URL""></a></td>
                                <td><div class="tts-meta" x-text="row.user || "unknown""></div><div class="tts-meta" x-text="row.post_id ? "post " + row.post_id : """></div></td>
                                <td><div class="tts-name" x-text="row.provider || """></div><div class="tts-meta" x-text="row.provider_key_last4 ? "key ..." + row.provider_key_last4 : """></div></td>
                                <td><a class="tts-meta" :href="row.audio_url" target="_blank" rel="noopener" x-text="row.audio_bytes ? row.audio_bytes + " bytes" : "pending""></a></td>
                                <td><div class="tts-meta" x-text="row.cost_usd === null ? "pending" : "$" + Number(row.cost_usd).toFixed(6)"></div></td>
                                <td><div class="tts-meta" x-text="row.created_at_est || row.created_at || """></div></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </section>


    <div class="tts-grid">
        <div class="space-y-4">
            <section class="tts-panel">
                <div class="tts-panel-head">
                    <div><div class="tts-title">1. Select Server</div><div class="tts-copy">Choose the WHM server that owns the target cPanel accounts.</div></div>
                </div>
                <div class="tts-panel-body">
                    <label>
                        <span class="tts-label">WHM Server</span>
                        <select x-model="serverId" class="tts-select" @change="resetSelections()">
                            <option value="">Select a server</option>
                            <template x-for="server in servers" :key="server.id">
                                <option :value="server.id" x-text="`${server.name} - ${server.hostname}`"></option>
                            </template>
                        </select>
                    </label>
                    <button type="button" class="tts-btn tts-btn-primary mt-3 w-full" @click="loadAccounts()" :disabled="busy.accounts || !serverId">
                        <span x-show="busy.accounts" class="tts-spin"></span><span x-text="busy.accounts ? 'Loading...' : 'Load cPanel Accounts'"></span>
                    </button>
                </div>
            </section>

            <section class="tts-panel">
                <div class="tts-panel-head">
                    <div><div class="tts-title">2. cPanel Accounts</div><div class="tts-copy">Select one or more accounts, then spawn the WordPress installs beneath them.</div></div>
                    <span class="tts-badge" x-text="accounts.length"></span>
                </div>
                <div class="tts-panel-body">
                    <div class="mb-3 flex flex-wrap gap-2">
                        <button type="button" class="tts-btn" @click="selectAllAccounts()" :disabled="!accounts.length">Select all</button>
                        <button type="button" class="tts-btn" @click="selectedAccounts=[]" :disabled="!selectedAccounts.length">Clear</button>
                    </div>
                    <div class="max-h-96 overflow-auto">
                        <template x-for="account in accounts" :key="account.username">
                            <label class="tts-row cursor-pointer">
                                <span>
                                    <span class="tts-name" x-text="account.username"></span>
                                    <span class="tts-meta" x-text="`${account.domain || 'no domain'} - ${account.status || 'unknown'} - ${account.package || 'no package'}`"></span>
                                </span>
                                <input type="checkbox" :value="account.username" x-model="selectedAccounts" class="mt-1 rounded border-gray-300 text-blue-600">
                            </label>
                        </template>
                        <div x-show="!accounts.length" class="tts-meta">No accounts loaded.</div>
                    </div>
                    <button type="button" class="tts-btn tts-btn-dark mt-3 w-full" @click="loadInstalls()" :disabled="busy.installs || !selectedAccounts.length">
                        <span x-show="busy.installs" class="tts-spin"></span><span x-text="busy.installs ? 'Discovering...' : 'Spawn WordPress Installs'"></span>
                    </button>
                </div>
            </section>
        </div>

        <div class="space-y-4">
            <section class="tts-panel">
                <div class="tts-panel-head">
                    <div><div class="tts-title">3. WordPress Installs</div><div class="tts-copy">Select installs and run detection. Scans are cached unless Force is checked.</div></div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="tts-btn" @click="selectAllInstalls()" :disabled="!installs.length">Select all</button>
                        <button type="button" class="tts-btn" @click="selectedInstallKeys=[]" :disabled="!selectedInstallKeys.length">Clear</button>
                    </div>
                </div>
                <div class="tts-panel-body">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" x-model="forceScan" class="rounded border-gray-300 text-blue-600">
                            Force fresh scan
                        </label>
                        <button type="button" class="tts-btn tts-btn-primary" @click="scanSelected()" :disabled="busy.scan || !selectedInstallKeys.length">
                            <span x-show="busy.scan" class="tts-spin"></span><span x-text="busy.scan ? 'Scanning...' : 'Run Detection and Integrity Check'"></span>
                        </button>
                    </div>
                    <div class="max-h-80 overflow-auto">
                        <template x-for="install in installs" :key="installKey(install)">
                            <label class="tts-row cursor-pointer">
                                <span>
                                    <span class="tts-name" x-text="install.url || install.path || `Install ${install.install_id}`"></span>
                                    <span class="tts-meta" x-text="`${install.account} - install ${install.install_id} - WP ${install.version || 'unknown'}`"></span>
                                </span>
                                <input type="checkbox" :value="installKey(install)" x-model="selectedInstallKeys" class="mt-1 rounded border-gray-300 text-blue-600">
                            </label>
                        </template>
                        <div x-show="!installs.length" class="tts-meta">No WordPress installs loaded.</div>
                    </div>
                </div>
            </section>

            <section class="tts-panel">
                <div class="tts-panel-head">
                    <div><div class="tts-title">4. Dashboard</div><div class="tts-copy">Plugin state, GitHub match, usage stats, credential status, one-click credential push, and GitHub update.</div></div>
                </div>
                <div class="tts-panel-body overflow-x-auto">
                    <table class="tts-table">
                        <thead>
                            <tr>
                                <th>Install</th>
                                <th>Plugin</th>
                                <th>GitHub Integrity</th>
                                <th>Stats</th>
                                <th>Credentials</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="result in results" :key="resultKey(result)">
                                <tr>
                                    <td>
                                        <div class="tts-name" x-text="result.target.url || result.target.path || `Install ${result.target.install_id}`"></div>
                                        <div class="tts-meta" x-text="`${result.target.account || ''} - install ${result.target.install_id}`"></div>
                                        <span x-show="result.cache_hit" class="tts-badge mt-2">cached</span>
                                    </td>
                                    <td>
                                        <span class="tts-badge" :class="pluginFound(result) ? 'tts-ok' : 'tts-bad'" x-text="pluginFound(result) ? 'installed' : 'missing'"></span>
                                        <div class="tts-meta mt-2" x-text="`local ${result.integrity?.local_version || 'n/a'} / remote ${result.integrity?.remote_version || 'n/a'}`"></div>
                                    </td>
                                    <td>
                                        <span class="tts-badge" :class="result.integrity?.matches ? 'tts-ok' : 'tts-warn'" x-text="result.integrity?.matches ? 'matches GitHub' : 'differs'"></span>
                                        <div class="tts-meta mt-2" x-text="diffSummary(result)"></div>
                                    </td>
                                    <td>
                                        <div class="tts-meta" x-text="`Published: ${result.stats?.published_posts ?? 0}`"></div>
                                        <div class="tts-meta" x-text="`Using plugin: ${result.stats?.posts_using_plugin ?? 0}`"></div>
                                        <div class="tts-meta" x-text="`Words: ${number(result.stats?.total_word_count ?? 0)}`"></div>
                                    </td>
                                    <td>
                                        <template x-for="row in credentialRows(result)" :key="row.id">
                                            <div class="mb-1 flex items-center justify-between gap-2">
                                                <span class="tts-meta" x-text="row.id"></span>
                                                <span class="tts-badge" :class="row.ok ? 'tts-ok' : 'tts-warn'" x-text="row.ok ? 'ready' : 'missing'"></span>
                                            </div>
                                        </template>
                                    </td>
                                    <td>
                                        <div class="flex flex-col gap-2">
                                            <button type="button" class="tts-btn" @click="scanOne(result.target, true)" :disabled="busy[`scan-${resultKey(result)}`]">Rescan</button>
                                            <button type="button" class="tts-btn tts-btn-primary" @click="pushCredentials(result)" :disabled="busy[`push-${resultKey(result)}`] || !pluginFound(result)">
                                                <span x-show="busy[`push-${resultKey(result)}`]" class="tts-spin"></span><span>Push Keys</span>
                                            </button>
                                            <button type="button" class="tts-btn tts-btn-warn" @click="updatePlugin(result)" :disabled="busy[`update-${resultKey(result)}`] || !pluginFound(result)">
                                                <span x-show="busy[`update-${resultKey(result)}`]" class="tts-spin tts-spin-dark"></span><span>Update From GitHub</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div x-show="!results.length" class="tts-meta">No scans yet.</div>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>

function smpTtsRealtime() {
    return {
        url: @json($requestPollUrl),
        active: [],
        requests: [],
        lastPoll: "",
        timer: null,
        init() {
            this.load();
            this.timer = window.setInterval(() => this.load(), 5000);
        },
        async load() {
            const response = await fetch(this.url, {headers: {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest"}});
            const data = await response.json();
            if (data && data.success) {
                this.active = data.active || [];
                this.requests = data.requests || [];
                this.lastPoll = new Date().toLocaleTimeString();
            }
        },
    };
}

function smpWordPressTtsDashboard() {
    return {
        servers: @json($servers),
        routes: {
            accounts: @json(route('smp-wordpress-tts.accounts')),
            installs: @json(route('smp-wordpress-tts.installs')),
            scan: @json(route('smp-wordpress-tts.scan')),
            push: @json(route('smp-wordpress-tts.push-credentials')),
            update: @json(route('smp-wordpress-tts.update-plugin')),
        },
        serverId: '',
        accounts: [],
        selectedAccounts: [],
        installs: [],
        selectedInstallKeys: [],
        results: [],
        forceScan: false,
        busy: {},
        message: '',
        messageOk: true,
        csrf() {
            return document.querySelector('meta[name=csrf-token]')?.content || '';
        },
        resetSelections() {
            this.accounts = [];
            this.selectedAccounts = [];
            this.installs = [];
            this.selectedInstallKeys = [];
            this.results = [];
        },
        installKey(install) {
            return `${install.server_id}:${install.install_id}:${install.account || ''}`;
        },
        resultKey(result) {
            return `${result.target?.server_id || ''}:${result.target?.install_id || ''}:${result.target?.account || ''}`;
        },
        selectedTargets() {
            return this.installs.filter((install) => this.selectedInstallKeys.includes(this.installKey(install)));
        },
        selectAllAccounts() {
            this.selectedAccounts = this.accounts.map((account) => account.username);
        },
        selectAllInstalls() {
            this.selectedInstallKeys = this.installs.map((install) => this.installKey(install));
        },
        async post(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrf(),
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Request failed.');
            }
            return data;
        },
        flash(message, ok = true) {
            this.message = message;
            this.messageOk = ok;
        },
        async loadAccounts() {
            this.busy.accounts = true;
            this.accounts = [];
            this.selectedAccounts = [];
            try {
                const data = await this.post(this.routes.accounts, {server_id: this.serverId});
                this.accounts = data.accounts || [];
                this.flash(`${this.accounts.length} cPanel account(s) loaded.`);
            } catch (error) {
                this.flash(error.message, false);
            }
            this.busy.accounts = false;
        },
        async loadInstalls() {
            this.busy.installs = true;
            this.installs = [];
            this.selectedInstallKeys = [];
            try {
                const data = await this.post(this.routes.installs, {server_id: this.serverId, account_usernames: this.selectedAccounts});
                this.installs = data.installs || [];
                this.flash(`${this.installs.length} WordPress install(s) discovered.`);
            } catch (error) {
                this.flash(error.message, false);
            }
            this.busy.installs = false;
        },
        async scanSelected() {
            this.busy.scan = true;
            try {
                for (const target of this.selectedTargets()) {
                    await this.scanOne(target, this.forceScan);
                }
                this.flash(`${this.selectedTargets().length} install(s) scanned.`);
            } catch (error) {
                this.flash(error.message, false);
            }
            this.busy.scan = false;
        },
        async scanOne(target, force = false) {
            const tempKey = `scan-${target.server_id}:${target.install_id}:${target.account || ''}`;
            this.busy[tempKey] = true;
            const data = await this.post(this.routes.scan, {targets: [target], force});
            const result = (data.results || [])[0];
            if (result) {
                this.upsertResult(result);
            }
            this.busy[tempKey] = false;
            return result;
        },
        upsertResult(result) {
            const key = this.resultKey(result);
            const index = this.results.findIndex((row) => this.resultKey(row) === key);
            if (index >= 0) {
                this.results.splice(index, 1, result);
            } else {
                this.results.push(result);
            }
        },
        async pushCredentials(result) {
            const key = `push-${this.resultKey(result)}`;
            this.busy[key] = true;
            try {
                const data = await this.post(this.routes.push, {target: result.target});
                this.flash(data.message || 'Credentials pushed.', !!data.success);
                await this.scanOne(result.target, true);
            } catch (error) {
                this.flash(error.message, false);
            }
            this.busy[key] = false;
        },
        async updatePlugin(result) {
            const key = `update-${this.resultKey(result)}`;
            this.busy[key] = true;
            try {
                const data = await this.post(this.routes.update, {target: result.target});
                this.flash(data.message || 'Plugin update completed.', !!data.success);
                await this.scanOne(result.target, true);
            } catch (error) {
                this.flash(error.message, false);
            }
            this.busy[key] = false;
        },
        pluginFound(result) {
            return !!(result.integrity?.installed?.plugin?.found);
        },
        diffSummary(result) {
            const integrity = result.integrity || {};
            return `Missing ${integrity.missing?.length || 0}, changed ${integrity.changed?.length || 0}, extra ${integrity.extra?.length || 0}`;
        },
        credentialRows(result) {
            const rows = [];
            const status = result.credential_status || {};
            Object.keys(status).forEach((provider) => {
                const configured = !!status[provider].configured;
                rows.push({id: provider, ok: configured});
            });
            return rows;
        },
        number(value) {
            return new Intl.NumberFormat().format(Number(value || 0));
        },
    };
}
</script>
@endpush
