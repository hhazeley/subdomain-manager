<div>
    <label>Record type</label>
    <select name="type" required data-dns-type>
        @foreach($recordTypes as $type)
            <option value="{{ $type }}" @selected(old('type', $values?->type) === $type)>{{ $type }}</option>
        @endforeach
    </select>
</div>
<div>
    <label>Record name</label>
    <input name="name" value="{{ old('name', $values?->name ?? '@') }}" placeholder="@ or www" required>
    <p class="mt-1 text-xs text-slate-500">@ means {{ $subdomain->fqdn }}. Relative names stay below this subdomain.</p>
</div>
<div class="md:col-span-2">
    <label>Content</label>
    <textarea name="content" rows="2" placeholder="192.0.2.1 or target.example.com">{{ old('content', $values?->content) }}</textarea>
</div>
<div>
    <label>TTL</label>
    <select name="ttl">
        @foreach([1 => 'Automatic', 60 => '1 minute', 300 => '5 minutes', 3600 => '1 hour', 86400 => '1 day'] as $ttl => $label)
            <option value="{{ $ttl }}" @selected((int) old('ttl', $values?->ttl ?? 1) === $ttl)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div data-dns-priority>
    <label>Priority (MX, SRV, URI)</label>
    <input type="number" min="0" max="65535" name="priority" value="{{ old('priority', $values?->priority) }}">
</div>
<label class="flex items-center gap-2">
    <input class="size-4 w-auto" type="checkbox" name="proxied" value="1" @checked(old('proxied', $values?->proxied))>
    <span>Cloudflare proxy (A, AAAA, CNAME)</span>
</label>
<div class="md:col-span-2" data-dns-structured>
    <label>Structured record data (JSON)</label>
    <textarea class="font-mono" name="data" rows="3" placeholder='Optional, e.g. {"service":"_sip","proto":"_tcp",...}'>{{ old('data', $values?->data ? json_encode($values->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
    <p class="mt-1 text-xs text-slate-500">Use Cloudflare's DNS record data object for structured types such as SRV or LOC.</p>
</div>
<div>
    <label>Comment</label>
    <input name="comment" maxlength="100" value="{{ old('comment', $values?->comment) }}">
</div>
<div>
    <label>Tags</label>
    <input name="tags" value="{{ old('tags', $values?->tags ? implode(', ', $values->tags) : '') }}" placeholder="owner:user, environment:prod">
</div>
