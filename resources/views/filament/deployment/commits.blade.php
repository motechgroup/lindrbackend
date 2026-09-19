<div style="font-family: monospace; font-size: 0.85rem; max-height: 400px; overflow-y: auto; background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 8px;">
    @forelse($commits as $c)
        <div style="margin-bottom: 10px; border-bottom: 1px solid #333; padding-bottom: 6px;">
            <div><strong style="color: #4ec9b0;">Commit:</strong> <span style="color: #ce9178;">{{ $c['short_hash'] }}</span> ({{ $c['full_hash'] }})</div>
            <div><strong style="color: #4ec9b0;">Author:</strong> {{ $c['author'] }} &lt;{{ $c['email'] }}&gt; | <em>{{ $c['date'] }}</em></div>
            <div><strong style="color: #4ec9b0;">Message:</strong> {{ $c['message'] }}</div>
        </div>
    @empty
        <div style="color: #94a3b8;">No commit history retrieved.</div>
    @endforelse
</div>
