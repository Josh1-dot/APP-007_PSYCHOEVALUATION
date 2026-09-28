<?php

namespace App\Services;

class Charts
{
    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    public static function radar(array $series, float $maximum = 100): ?string
    {
        $first = reset($series);
        if (! $first || count($first) < 3 || $maximum <= 0) {
            return null;
        }
        $labels = array_keys($first);
        $count = count($labels);
        $cx = 210;
        $cy = 170;
        $radius = 112;
        $point = static function (int $i, float $value) use ($count, $cx, $cy, $radius): string {
            $angle = -M_PI / 2 + 2 * M_PI * $i / $count;
            $ratio = max(0, min(1, $value));

            return round($cx + cos($angle) * $radius * $ratio, 2).','.round($cy + sin($angle) * $radius * $ratio, 2);
        };
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 420 370" role="img" aria-label="Comparaison des dimensions en radar"><title>Profil par dimension</title><rect width="420" height="370" fill="white"/>';
        for ($level = 1; $level <= 4; $level++) {
            $points = [];
            foreach ($labels as $i => $label) {
                $points[] = $point($i, $level / 4);
            }$svg .= '<polygon points="'.implode(' ', $points).'" fill="none" stroke="#dce6e0"/>';
        }
        foreach ($labels as $i => $label) {
            $p = explode(',', $point($i, 1));
            $angle = -M_PI / 2 + 2 * M_PI * $i / $count;
            $x = round($cx + cos($angle) * ($radius + 29), 2);
            $y = round($cy + sin($angle) * ($radius + 29), 2);
            $svg .= '<line x1="210" y1="170" x2="'.$p[0].'" y2="'.$p[1].'" stroke="#e4ece6"/><text x="'.$x.'" y="'.$y.'" font-size="11" text-anchor="middle" fill="#466359">'.self::escape(mb_substr((string) $label, 0, 18)).'</text>';
        }
        $colors = ['#277a6b', '#8663ad', '#b27925'];
        $index = 0;
        foreach ($series as $name => $scores) {
            $points = [];
            foreach ($labels as $i => $label) {
                $points[] = $point($i, (float) ($scores[$label] ?? 0) / $maximum);
            }$color = $colors[$index % 3];
            $svg .= '<polygon points="'.implode(' ', $points).'" fill="'.$color.'" fill-opacity="0.10" stroke="'.$color.'" stroke-width="2"/><text x="20" y="'.(330 + $index * 16).'" font-size="11" fill="'.$color.'">'.self::escape(mb_substr((string) $name, 0, 55)).'</text>';
            $index++;
            if ($index === 3) {
                break;
            }
        }

        return $svg.'</svg>';
    }

    public static function line(array $rows, float $maximum = 100): ?string
    {
        if (count($rows) < 2 || $maximum <= 0) {
            return null;
        }$labels = array_keys($rows[0]['scores']);
        $rows = array_slice($rows, -12);
        $count = count($rows);
        $colors = ['#277a6b', '#8663ad', '#b27925', '#467fa2', '#c56d83', '#798b3f', '#666666', '#864f33', '#31535b'];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 330" role="img" aria-label="Évolution des scores entre les passations"><title>Évolution des scores</title><rect width="640" height="330" fill="white"/>';
        for ($i = 0; $i <= 4; $i++) {
            $y = 240 - 200 * $i / 4;
            $svg .= '<line x1="50" y1="'.$y.'" x2="600" y2="'.$y.'" stroke="#e4ece6"/><text x="42" y="'.($y + 4).'" text-anchor="end" font-size="10">'.round($maximum * $i / 4, 1).'</text>';
        }
        foreach ($labels as $i => $label) {
            $points = [];
            foreach ($rows as $j => $row) {
                $x = 50 + 550 * $j / ($count - 1);
                $y = 240 - 200 * max(0, min(1, (float) ($row['scores'][$label] ?? 0) / $maximum));
                $points[] = round($x, 2).','.round($y, 2);
            }$color = $colors[$i % 9];
            $svg .= '<polyline points="'.implode(' ', $points).'" fill="none" stroke="'.$color.'" stroke-width="2"/><text x="'.(50 + ($i % 5) * 110).'" y="'.(295 + intdiv($i, 5) * 17).'" fill="'.$color.'" font-size="11">'.self::escape(mb_substr((string) $label, 0, 16)).'</text>';
        }
        foreach ($rows as $i => $row) {
            $svg .= '<text x="'.round(50 + 550 * $i / ($count - 1), 2).'" y="260" text-anchor="middle" font-size="9">'.self::escape($row['date']).'</text>';
        }

        return $svg.'</svg>';
    }

    public static function dataUri(?string $svg): ?string
    {
        return $svg ? 'data:image/svg+xml;base64,'.base64_encode($svg) : null;
    }
}
