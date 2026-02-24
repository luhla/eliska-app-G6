<?php

declare(strict_types=1);

namespace App\Router;

use Nette\Application\Routers\RouteList;

final class RouterFactory
{
    public static function createRouter(): RouteList
    {
        $router = new RouteList;

        // Admin routes
        $router->addRoute('/admin/login[/<action>]', 'Admin:Login:signIn');
        $router->addRoute('/admin/blocks[/<action>[/<id \d+>]]', 'Admin:Block:default');
        $router->addRoute('/admin/groups[/<action>[/<id \d+>]]', 'Admin:Group:default');
        $router->addRoute('/admin/settings[/<action>]', 'Admin:Settings:default');
        $router->addRoute('/admin/users[/<action>[/<uid>]]', 'Admin:User:default');
        $router->addRoute('/admin[/<action>]', 'Admin:Dashboard:default');

        // User route
        $router->addRoute('/go/<uid>', 'User:Go:default');

        return $router;
    }
}
