<?php

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function day_code_to_label($code) {
    $map = [
        'MON' => '월',
        'TUE' => '화',
        'WED' => '수',
        'THU' => '목',
        'FRI' => '금',
        'SAT' => '토',
        'SUN' => '일',
    ];

    return $map[$code] ?? $code;
}

function render_stars($rating, $max = 5) {
    $rating = max(0, min((float)$rating, $max));
    $html = '<span class="star-row" aria-label="별점 ' . h(number_format($rating, 1)) . '점">';

    for ($i = 1; $i <= $max; $i++) {
        if ($rating >= $i) {
            $html .= '<span class="star is-full">★</span>';
        } elseif ($rating >= $i - 0.5) {
            $html .= '<span class="star is-half">★</span>';
        } else {
            $html .= '<span class="star is-empty">★</span>';
        }
    }

    $html .= '</span>';
    return $html;
}

function valid_day_codes() {
    return ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
}

function is_valid_day_code($code) {
    return in_array($code, valid_day_codes(), true);
}
