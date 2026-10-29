<div class="card-tools hidden-print">
    <div class="btn-group">
        <a href="#" class="btn btn-info dropdown-toggle" data-toggle="dropdown">
            {{ trans_choice('accounting::lang.action', 2) }}
            <i class="fa fa-caret-down"></i>
        </a>
        @php
            $filter_string = !empty($filters) ? '&' . $filters : '';
        @endphp
        <div class="dropdown-menu dropdown-menu-xs dropdown-menu-right">
            @if (empty($options) || in_array('csv', $options))
                <a href="{{ url($url . '?type=csv&download=1' . $filter_string) }}" class="dropdown-item hello">
                    {{ trans_choice('accounting::lang.download', 1) }}
                    {{ trans_choice('accounting::lang.csv_format', 1) }}</a>
            @endif
            @if (empty($options) || in_array('excel', $options))
                <a href="{{ url($url . '?type=excel&download=1' . $filter_string) }}"
                    class="dropdown-item">{{ trans_choice('accounting::lang.download', 1) }}
                    {{ trans_choice('accounting::lang.excel_format', 1) }}</a>
            @endif
            @if (empty($options) || in_array('excel_2007', $options))
                <a href="{{ url($url . '?type=excel_2007&download=1' . $filter_string) }}"
                    class="dropdown-item">{{ trans_choice('accounting::lang.download', 1) }}
                    {{ trans_choice('accounting::lang.excel_2007_format', 1) }}</a>
            @endif
            @if (empty($options) || in_array('pdf', $options))
                <a href="{{ url($url . '?type=pdf&download=1' . $filter_string) }}"
                    class="dropdown-item">{{ trans_choice('accounting::lang.download', 1) }}
                    {{ trans_choice('accounting::lang.pdf_format', 1) }}</a>
            @endif
        </div>
    </div>
</div>
