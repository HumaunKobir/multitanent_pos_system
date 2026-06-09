<?php

namespace App\Support;

final class MoneyFormat
{
    private static ?string $symbolDataUri = null;

    public static function bdt(float|int|string $amount, int $decimals = 2): string
    {
        return self::symbolHtml().number_format((float) $amount, $decimals);
    }

    public static function symbolHtml(): string
    {
        return '<img src="'.self::symbolDataUri().'" width="9" height="11" style="vertical-align:-1px;margin-right:2px;" alt="Tk">';
    }

    public static function symbolDataUri(): string
    {
        if (self::$symbolDataUri !== null) {
            return self::$symbolDataUri;
        }

        $fontPath = resource_path('fonts/NotoSansBengali-Regular.ttf');

        if (extension_loaded('gd') && is_file($fontPath)) {
            $image = imagecreatetruecolor(18, 18);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefill($image, 0, 0, $transparent);
            $color = imagecolorallocate($image, 30, 41, 59);
            imagettftext($image, 11, 0, 1, 14, $color, $fontPath, '৳');

            ob_start();
            imagepng($image);
            $png = ob_get_clean();
            imagedestroy($image);

            if ($png !== false) {
                self::$symbolDataUri = 'data:image/png;base64,'.base64_encode($png);

                return self::$symbolDataUri;
            }
        }

        self::$symbolDataUri = 'data:image/svg+xml;base64,'.base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="12" viewBox="0 0 10 12"><text x="0" y="10" font-size="10" font-weight="700" fill="#1e293b">Tk</text></svg>'
        );

        return self::$symbolDataUri;
    }
}
