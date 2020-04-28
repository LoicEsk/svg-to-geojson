<?php
declare(strict_types=1);

namespace App\Application\Actions\Convert;

use App\Application\Actions\Action;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Doctrine\ORM\EntityManager;
use App\Domain\Entity;

class DoConvert extends Action
{
    protected $alerts;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger)
    {
        parent::__construct($container, $logger, null);
    }

    protected function action() : Response {
        $codeSVG = isset( $_POST[ 'svg-code' ] ) ? $_POST[ 'svg-code' ] : "";

        $geojson = [];
        $paths = [];
        if( preg_match_all( '/<path .*d=["\'](.*)["\'].*>/miU', $codeSVG, $paths ) ) {
            foreach( $paths[1] as $path ) {
                $geojson[] = $this->decodeSvgPath( $path );
            }
        }
        
        ob_flush();
        return $this->container->get('view')->render($this->response, 'result.html', [
            'title'     => "Résultat",
            'alerts'    => $this->alerts,
            'codeSVG'   => $codeSVG,
            'codeGEOJSON'  => json_encode( $geojson )
        ]);
    }

    protected function decodeSvgPath( $code ) {
        $codeChaine = preg_replace( '/[a-z]/i', ';$0:', $code );
        $codes = explode( ';', $codeChaine );
        $codes = array_map( function( $c ){
            return explode( ':', $c );
        }, $codes );
        //  echo '<pre>'; var_dump( $codes ); echo '</pre>'; 
        $points = [];
        $currentP = [ 0, 0 ];
        foreach( $codes  as $instruction ) {
            $values = [];
            if( count( $instruction ) === 2 ) {
                if( preg_match_all( '/[-+]?[0-9]*\.?[0-9]*/', $instruction[1], $values ) ) {
                    // var_dump( $values );
                    switch( $instruction[0] ) {
                        case 'M':
                            // déplacement sans tracé en absolu
                            $currentP[ 0 ] = $values[ 0 ][ 0 ];
                            $currentP[ 1 ] = $values[ 0 ][ 1 ];
                        break;
                        case 'm' :
                            // déplacement sans tracé en relatif
                            $currentP[ 0 ] += $values[ 0 ];
                            $currentP[ 1 ] += $values[ 1 ];
                        break;
                        // case 'L':
                        //     //déplacement avec tracé en absolu
                        // break;
                        // case 'l':
                        //     // déplacement avec tracé en relatif
                        // break;
                        default :
                            $this->alerts[] = [
                                'type' => 'danger',
                                'text' => "Un tracé n'a pas pu être converti : " . json_encode( $instruction )
                            ];
                    }
                    $points[] = $currentP;
                }
            } else {
                $this->alerts[] = [
                    'type' => 'warning',
                    'text' => "Un tracé n'a pas pu être converti : " . json_encode( $instruction )
                ];
            }
        }
        return $points;
    }
}