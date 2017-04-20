<?php

namespace Stichoza\GoogleTranslate\Tokens;

/**
 * Google Token Generator.
 *
 * Thanks to @helen5106 and @tehmaestro and few other cool guys
 * at https://github.com/Stichoza/google-translate-php/issues/32
 */
class GoogleTokenGenerator implements TokenProviderInterface
{
    /**
     * UTF-16 Code Units
     *
     * @var array
     */
    private $utf16CodeUnits = [];

    /**
     * Generate and return a token.
     *
     * @param string $source Source language
     * @param string $target Target language
     * @param string $text   Text to translate
     *
     * @return mixed A token
     */
    public function generateToken($source, $target, $text)
    {
        $escapedText = substr(json_encode($text), 1, -1);
        preg_match_all("/\\\\u[0-9a-fA-F]{4}|./mi", $escapedText, $matches);
        $this->utf16CodeUnits = $matches[0];

        return $this->TL($text);
    }

    /**
     * Generate a valid Google Translate request token.
     *
     * @param string $a text to translate
     *
     * @return string
     */
    private function TL($a)
    {
        $tkk = $this->TKK();
        $b = $tkk[0];

        for ($d = [], $e = 0, $f = 0; $f < count($this->utf16CodeUnits); $f++) {
            $g = $this->charCodeAtUTF16CodeUnit($f);
            if (128 > $g) {
                $d[$e++] = $g;
            } else {
                if (2048 > $g) {
                    $d[$e++] = $g >> 6 | 192;
                } else {
                    if (55296 == ($g & 64512) && $f + 1 < count($this->utf16CodeUnits) && 56320 == ($this->charCodeAtUTF16CodeUnit($f + 1) & 64512)) {
                        $g = 65536 + (($g & 1023) << 10) + ($this->charCodeAtUTF16CodeUnit(++$f) & 1023);
                        $d[$e++] = $g >> 18 | 240;
                        $d[$e++] = $g >> 12 & 63 | 128;
                    } else {
                        $d[$e++] = $g >> 12 | 224;
                        $d[$e++] = $g >> 6 & 63 | 128;
                    }
                }
                $d[$e++] = $g & 63 | 128;
            }
        }
        $a = $b;
        for ($e = 0; $e < count($d); $e++) {
            $a += $d[$e];
            $a = $this->RL($a, '+-a^+6');
        }
        $a = $this->RL($a, '+-3^+b+-f');
        $a ^= $tkk[1];
        if (0 > $a) {
            $a = ($a & 2147483647) + 2147483648;
        }
        $a = fmod($a, pow(10, 6));

        return $a.'.'.($a ^ $b);
    }

    /**
     * @return array
     */
    private function TKK()
    {
        return ['406398', (561666268 + 1526272306)];
    }

    /**
     * Process token data by applying multiple operations.
     *
     * @param $a
     * @param $b
     *
     * @return int
     */
    private function RL($a, $b)
    {
        for ($c = 0; $c < strlen($b) - 2; $c += 3) {
            $d = $b{$c + 2};
            $d = $d >= 'a' ? $this->charCodeAt($d, 0) - 87 : intval($d);
            $d = $b{$c + 1}
            == '+' ? $this->shr32($a, $d) : $a << $d;
            $a = $b{$c}
            == '+' ? ($a + $d & 4294967295) : $a ^ $d;
        }

        return $a;
    }

    /**
     * Crypto function.
     *
     * @param $x
     * @param $bits
     *
     * @return number
     */
    private function shr32($x, $bits)
    {
        if ($bits <= 0) {
            return $x;
        }
        if ($bits >= 32) {
            return 0;
        }
        $bin = decbin($x);
        $l = strlen($bin);
        if ($l > 32) {
            $bin = substr($bin, $l - 32, 32);
        } elseif ($l < 32) {
            $bin = str_pad($bin, 32, '0', STR_PAD_LEFT);
        }

        return bindec(str_pad(substr($bin, 0, 32 - $bits), 32, '0', STR_PAD_LEFT));
    }

    /**
     * Get the Unicode of the character at the specified index in the UTF-16 code unit array.
     *
     * @param string $str
     * @param int    $index
     *
     * @return null|number
     */
    private function charCodeAtUTF16CodeUnit($index)
    {
        $unit = $this->utf16CodeUnits[$index];
        
        if(strlen($unit) > 1) {
            $hex = substr($unit, 2);
            return hexdec($hex);
        }
        else {
            return ord($unit);
        }
    }
}
