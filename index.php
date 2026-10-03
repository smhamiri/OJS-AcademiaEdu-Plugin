<?php

/**
 * Plugin loader for OJS 3.4+/3.5: index.php MUST return the plugin instance.
 */

require_once(__DIR__ . '/AcademiaEduPlugin.php');

return new \APP\plugins\generic\academiaEdu\AcademiaEduPlugin();
