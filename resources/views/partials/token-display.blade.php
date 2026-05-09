<div style="padding: 0.25rem 0 1rem;">

    <p style="font-size: 0.875rem; color: #374151; margin: 0 0 1rem 0; line-height: 1.6;">
        Your token has been generated. Copy it now —
        <strong style="color: #111827;">it will not be shown again.</strong>
    </p>

    <div
        x-data="{
            token: @js($token),
            copied: false,
            async copy() {
                await navigator.clipboard.writeText(this.token);
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            }
        }"
        style="display: flex; align-items: center; gap: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 0.75rem;"
    >
        <code style="flex: 1; font-family: ui-monospace, monospace; font-size: 0.8rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.01em;">
            {{ $token }}
        </code>

        <button
            type="button"
            x-on:click="copy"
            style="flex-shrink: 0; padding: 0.375rem 0.875rem; font-size: 0.8125rem; font-weight: 500; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: #374151; cursor: pointer; white-space: nowrap;"
            x-bind:style="copied ? 'background:#f0fdf4;border-color:#86efac;color:#15803d;' : ''"
        >
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak>Copied</span>
        </button>
    </div>

    <p style="margin: 0; font-size: 0.8125rem; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 0.5rem 0.75rem;">
        Treat this token like a password. Store it in a secret manager, not in source code.
    </p>

    <hr style="margin: 1.25rem 0 0; border: none; border-top: 1px solid #f1f5f9;">

</div>

<style>[x-cloak]{display:none!important}</style>
