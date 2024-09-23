<?php

/*
 * This file is part of Free Downloads.
 *
 * Copyright (c) Richard Webster
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use SOM\FreeDownloads\{
    Plugin,
    Project,
    Loaders\Autoloader,
    Loaders\FileLoader
};

use const DIRECTORY_SEPARATOR as SEP;

defined('ABSPATH') || exit;

(function(){
    $includes = 'Includes' . SEP;

    require_once $includes . 'Project.php';
    $project = new Project(__DIR__ . SEP . 'project.json', __DIR__);
    $project->buildProject();
    
    define('SOMDN_PLUGIN_VER', $project->getVersion());
    define('SOMDN_BASE', 'woocommerce');
    define('SOMDN_PLUGIN_NAME_FULL', $project->getName());
    define('SOMDN_PATH', plugin_dir_path(SOMDN_FILE));
    define('SOMDN_PLUGIN_PATH', plugin_basename(dirname(SOMDN_FILE)));
    define('SOMDN_PLUGIN_BASENAME', plugin_basename(SOMDN_FILE));
    
    require_once $includes . 'Loaders' . SEP . 'Autoloader.php';
    (new Autoloader($project->getAutoloaderSources()))->register();
    
    (new FileLoader(...$project->getFileSources()))->loadFiles();
    
    somdn($project);
})();

function somdn(?Project $project = null): ?Plugin
{
    return Plugin::instance($project);
}
