<?php
declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

use App\Application\Actions\Convert\DoConvert;


return function (App $app) {

    $app->get('/', function (Request $request, Response $response) use ( $app ) {
        $container = $app->getContainer();
        return $container->get('view')->render($response, 'form.html', [
            'title' => "Convertion du SVG vers le geoJSON",
        ]);
    });

    $app->post( '/convert[/]', DoConvert::class );

    // $app->group('/users', function (Group $group) {
    //     $group->get('', ListUsersAction::class);
    //     $group->get('/{id}', ViewUserAction::class);
    // });
};
