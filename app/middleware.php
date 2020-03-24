<?php
declare(strict_types=1);

use App\Application\Middleware\SessionMiddleware;
use Slim\App;
use Tuupola\Middleware\HttpBasicAuthentication\AuthenticatorInterface;
use Tuupola\Middleware\HttpBasicAuthentication;

use Psr\Container\ContainerInterface;
use Doctrine\ORM\EntityManager;
use App\Domain\Entity\User;

class UserAuthenticator implements AuthenticatorInterface {
    protected $app;

    public function __construct( $app ) {
        $this->app = $app;
    }
    public function __invoke(array $arguments ): bool {
        // var_dump( $arguments );
        
        $login = $arguments[ 'user' ];
        $pass = $arguments[ 'password' ];
        if( !($login && $pass) ) return false;
        $cryptedPass = md5( $arguments[ 'password' ]);
        // var_dump( $cryptedPass );
        
        $container = $this->app->getContainer();
        $em = $container->get(EntityManager::class);
        $user = $em->getRepository( User::class )->findOneBy( [
            "login" => $login,
            "pass"  => $cryptedPass
        ]);

        return ( $user instanceof User );
    }
}

return function (App $app) {
    $app->add(SessionMiddleware::class);

    /**
     * Sécurisation avec une autentication basique
     * Décommenter les lignes ci-dessous pour l'activer
     */
    // $app->add(new HttpBasicAuthentication([
    //     "secure" => true,
    //     "relaxed" => ["localhost"],
    //     "path"      => "/",         // protéger tout
    //     // "ignore"    => ['/api'],   // sauf l'api'
    //     "realm"     => "Protected",
    //     "authenticator" => new UserAuthenticator( $app )
    // ]));
};
