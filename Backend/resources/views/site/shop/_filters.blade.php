@foreach($filterGroups as $g)
    <div class="fg" data-group="{{ $g['label'] }}">
        <button type="button" @class(['fg-btn', 'on' => $g['count']]) aria-expanded="false" data-fg-toggle>{{ $g['label'] }}@if($g['count'])<span class="fg-n">{{ $g['count'] }}</span>@endif<x-icon d="M6 9l6 6 6-6" size="14" stroke="2" /></button>
        <fieldset class="fg-pop">
            <legend class="fg-head"><span>{{ $g['label'] }}</span></legend>
            @foreach($g['options'] as $o)
                <label class="fg-opt">
                    <input type="checkbox" name="f[{{ $g['label'] }}][]" value="{{ $o['label'] }}" @checked($o['on'])>
                    @if($o['hex'])<span aria-hidden="true" class="sw" style="background:{{ $o['hex'] }}"></span>@endif
                    <span class="grow">{{ $o['label'] }}</span>
                    <span class="fg-c">{{ $o['count'] }}</span>
                </label>
            @endforeach
        </fieldset>
    </div>
@endforeach
