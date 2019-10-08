<?php

/**
 * This file is part of the YConverter package.
 *
 * @author (c) Yakamara Media GmbH & Co. KG
 * @author Thomas Blum <thomas.blum@yakamara.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$metaInfo = rex_addon::get('metainfo');
if (!rex::isBackend() && $metaInfo->isAvailable()) {
    require_once($metaInfo->getPath('/functions/function_metainfo.php'));

}
