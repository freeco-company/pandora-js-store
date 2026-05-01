<?php

namespace App\Services;

use Pandora\Shared\Compliance\LegalContentSanitizer as BaseSanitizer;

/**
 * 集團合規 sanitizer — canonical 實作搬到 freeco/pandora-shared 套件
 * （見 packages/pandora-shared/src/Compliance/LegalContentSanitizer.php
 * + docs/group-fp-product-compliance.md）。本類別保留 App\Services 下的 alias
 * 是為了既有 binding / Observer / Command 不需大量改 use；新 code 請直接 import
 * Pandora\Shared\Compliance\LegalContentSanitizer。
 */
class LegalContentSanitizer extends BaseSanitizer
{
}
