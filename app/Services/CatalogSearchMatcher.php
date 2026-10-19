<?php

namespace App\Services;

class CatalogSearchMatcher
{
    public function eligible(string $term): bool
    {
        $term = $this->normalize($term);

        $length = grapheme_strlen($term);

        return is_int($length) && $length >= 4 && preg_match('/^[\p{L}\p{M}\p{N}]+$/u', $term) === 1;
    }

    public function matches(string $term, string $text): bool
    {
        if (preg_match('//u', $term) !== 1 || preg_match('//u', $text) !== 1) {
            return false;
        }

        $letters = mb_str_split(mb_strtolower($this->normalize($term)));
        if (preg_match_all('/[\p{L}\p{M}\p{N}]+/u', mb_strtolower($this->normalize($text)), $words) === false) {
            return false;
        }
        foreach ($words[0] as $word) {
            if ($this->oneEdit($letters, mb_str_split($word))) {
                return true;
            }
        }

        return false;
    }

    private function oneEdit(array $left, array $right): bool
    {
        if (abs(count($left) - count($right)) > 1) {
            return false;
        }
        $index = 0;
        while (isset($left[$index], $right[$index]) && $left[$index] === $right[$index]) {
            $index++;
        }
        if ($index === min(count($left), count($right))) {
            return true;
        }
        if (count($left) > count($right)) {
            return array_slice($left, $index + 1) === array_slice($right, $index);
        }
        if (count($right) > count($left)) {
            return array_slice($left, $index) === array_slice($right, $index + 1);
        }
        if (array_slice($left, $index + 1) === array_slice($right, $index + 1)) {
            return true;
        }

        return isset($left[$index + 1], $right[$index + 1])
            && $left[$index] === $right[$index + 1] && $left[$index + 1] === $right[$index]
            && array_slice($left, $index + 2) === array_slice($right, $index + 2);
    }

    private function normalize(string $value): string
    {
        $normalized = normalizer_normalize($value, \Normalizer::FORM_C);

        return $normalized !== false ? $normalized : $value;
    }
}
