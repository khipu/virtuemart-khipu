sed -i '' 's@userAgent = "khipu-api-php-client/2.9.1"@userAgent = "virtuemart/3.x"@g' vendor/khipu/khipu-api-client/lib/Configuration.php
zip -r khipu.zip khipu.php khipu.zip khipu.xml vendor
