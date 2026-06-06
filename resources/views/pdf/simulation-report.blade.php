<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulation Report</title>
    <style>
        {!! file_get_contents(resource_path('css/pdf-simulation-report.css')) !!}
    </style>
</head>
<body>
    @php
        $safe = fn($val) => $val === null || $val === '' ? 'N/A' : $val;
    @endphp

    <!-- Header -->
    <header>
        <h1>Simulation Report</h1>
        <div class="info-block">
            <p><strong>Generated:</strong> {{ $exportedAt ?? 'N/A' }}</p>
            <p><strong>Simulation ID:</strong> Current Active Grid</p>
            <p><strong>Total QoL Score:</strong> 
                @if (isset($qolData['total_score']))
                    <span class="@if($qolData['total_score'] > 0) score-positive @elseif($qolData['total_score'] < 0) score-negative @else score-neutral @endif">
                        {{ $qolData['total_score'] >= 0 ? '+' : '' }}{{ $qolData['total_score'] }}
                    </span>
                @else
                    <span class="placeholder-text">N/A</span>
                @endif
            </p>
        </div>
        <p>This report contains the current simulation grid layout, effects summary, and detailed Quality of Life analysis. All data is accurate as of the export date.</p>
    </header>

    <!-- Grid visualization-->
    <section aria-labelledby="grid-heading">
        <h2 id="grid-heading">Grid Visualization</h2>
        <p>The following table shows the current 4x3 city grid with assigned functions and categories. Empty cells are marked as <span class="placeholder-text">N/A</span>.</p>
        
        <table role="table" aria-describedby="grid-table-desc">
            <caption id="grid-table-desc" class="sr-only">Current city grid layout showing function placement across 4 columns and 3 rows.</caption>
            <thead>
                <tr>
                    <th scope="col">Cell Position</th>
                    <th scope="col">Column 1</th>
                    <th scope="col">Column 2</th>
                    <th scope="col">Column 3</th>
                    <th scope="col">Column 4</th>
                </tr>
            </thead>
            <tbody>
                @for ($row = 1; $row <= 3; $row++)
                    <tr>
                        <th scope="row">Row {{ $row }}</th>
                        @for ($col = 1; $col <= 4; $col++)
                            @php
                                $cell = $grid->first(fn($c) => intval($c->row) === $row && intval($c->col) === $col);
                                $hasFunction = $cell && $cell->function;
                                $functionName = $hasFunction ? $cell->function->name : null;
                                $functionCategory = $hasFunction ? $cell->function->category : null;
                                $imageSrc = $hasFunction && isset($cell->function->image_base64) ? $cell->function->image_base64 : null;
                            @endphp
                            <td class="cell-content">
                                @if ($imageSrc)
                                    <img class="grid-cell-img" src="{{ $imageSrc }}" alt="{{ $functionName }} icon">
                                @endif
                                <div class="@if($hasFunction) cell-function @else cell-empty @endif">
                                    {{ $safe($functionName) }}
                                </div>
                                @if ($hasFunction)
                                    <div class="cell-category">{{ $safe($functionCategory) }}</div>
                                @endif
                            </td>
                        @endfor
                    </tr>
                @endfor
            </tbody>
        </table>
    </section>

    <!-- Effects summary -->
    <section aria-labelledby="effects-heading">
        <h2 id="effects-heading">Effects Summary</h2>
        <p>This section summarizes the impact of each function category on the simulation's Quality of Life.</p>
        
        <table role="table" aria-describedby="effects-table-desc">
            <caption id="effects-table-desc" class="sr-only">Summary of QoL effects by category, including component values and category totals.</caption>
            <thead>
                <tr>
                    <th scope="col">Category</th>
                    <th scope="col">Score</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($qolData['categories'] ?? [] as $categoryName => $data)
                    @php
                        $total = $data['total'] ?? 0;
                        $items = $data['items'] ?? [];
                    @endphp
                    <tr>
                        <th scope="row">{{ $categoryName }}</th>
                        <td class="@if($total > 0) score-positive @elseif($total < 0) score-negative @else score-neutral @endif">
                            {{ $total >= 0 ? '+' : '' }}{{ $total }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="placeholder-text">No effects data available for this simulation.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <!-- Breakdown QoL -->
    <section aria-labelledby="qol-heading">
        <h2 id="qol-heading">Detailed Quality of Life Breakdown</h2>
        <p>This detailed breakdown shows each factor contributing to the overall Quality of Life score for the simulation.</p>
        
        <table role="table" aria-describedby="qol-table-desc">
            <caption id="qol-table-desc" class="sr-only">Detailed QoL breakdown by category, showing individual effects and their contributions to the total score.</caption>
            <thead>
                <tr>
                    <th scope="col">Category</th>
                    <th scope="col">Effect Factor</th>
                    <th scope="col">Impact Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($qolData['categories'] ?? [] as $categoryName => $data)
                    @php $items = $data['items'] ?? []; @endphp
                    @if (count($items) > 0)
                        @foreach ($items as $item)
                            <tr>
                                <th scope="row">{{ $categoryName }}</th>
                                <td>{{ $safe($item['function'] ?? $item['description'] ?? $item['label'] ?? 'Effect') }}</td>
                                <td class="@if(($item['value'] ?? $item['impact'] ?? 0) > 0) score-positive @elseif(($item['value'] ?? $item['impact'] ?? 0) < 0) score-negative @else score-neutral @endif">
                                    {{ ($item['value'] ?? $item['impact'] ?? 0) >= 0 ? '+' : '' }}{{ $safe($item['value'] ?? $item['impact'] ?? '0') }}
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <th scope="row">{{ $categoryName }}</th>
                            <td colspan="2" class="placeholder-text">No specific effects recorded for this category.</td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="3" class="placeholder-text">No detailed QoL data available. Please run the simulation to populate this report.</td>
                    </tr>
                @endforelse
                <tr style="border-top: 2px solid #333; font-weight: 700;">
                    <th scope="row" colspan="2">Total QoL Score</th>
                    <td class="@if(($qolData['total_score'] ?? 0) > 0) score-positive @elseif(($qolData['total_score'] ?? 0) < 0) score-negative @else score-neutral @endif">
                        {{ ($qolData['total_score'] ?? 0) >= 0 ? '+' : '' }}{{ $safe($qolData['total_score'] ?? '0') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </section>

    <!-- Accessibility for screen readers -->
    <div class="sr-only">
        <h3>Accessibility Note</h3>
        <p>This PDF report is structured with semantic headings, descriptive table captions, and proper heading hierarchy to support screen readers and accessible PDF viewers. All numerical data is properly labeled with context to aid comprehension.</p>
    </div>
</body>
</html>
