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
        switch($_POST['typeConversion']){
          case 'convertirSvg':
            if( preg_match_all( '/<polygon .*id=["\'](.*)["\'] .*points=["\'](.*)["\'].*>/miU', $codeSVG, $polygons ) ) {
              if( preg_match_all( '/<a .*href=["\'](.*)["\'].*>/miU', $codeSVG, $liens ) ) {
                  $listePolygons = $polygons[2];
                  $listeIDs = $polygons[1];
                  $listeLiens = $liens[1];
                  for($i = 0;$i<sizeof($listePolygons);$i++){
                    if(array_key_exists($i,$listeLiens) && array_key_exists($i,$listeIDs)){
                      $geojson[] = [
                        "type" => "Feature",
                        "properties" => ["url"=>$listeLiens[$i],"id"=>$listeIDs[$i]],
                        "geometry"=> [
                          "type"=> "MultiPolygon",
                          "coordinates"=>[
                          $this->decodeSvgPolygon( $listePolygons[$i] )]
                        ]
                      ];
                    } else {
                      $geojson[] = [
                        "type" => "Feature",
                        "properties" => ["url"=>'',"id"=>''],
                        "geometry"=> [
                          "type"=> "MultiPolygon",
                          "coordinates"=>[
                          $this->decodeSvgPolygon( $listePolygons[$i] )]
                        ]
                      ];
                    }
                  }
              } else {
                $listePolygons = $polygons[2];
                for($i = 0;$i<sizeof($listePolygons);$i++){
                  if(array_key_exists($i,$listeIDs)){
                    $geojson[] = [
                      "type" => "Feature",
                      "properties" => ["id"=>$listeIDs[$i]],
                      "geometry"=> [
                        "type"=> "MultiPolygon",
                        "coordinates"=>[
                        $this->decodeSvgPolygon( $listePolygons[$i] )]
                      ]
                    ];
                  } else {
                    $geojson[] = [
                      "type" => "Feature",
                      "geometry"=> [
                        "type"=> "MultiPolygon",
                        "coordinates"=>[
                        $this->decodeSvgPolygon( $listePolygons[$i] )]
                      ]
                    ];
                  }
                }
              }
            }

            if( preg_match_all( '/<path .* d=["\'](.*)["\'].*>/miU', $codeSVG, $paths ) ){
              if( preg_match_all( '/<a .*href=["\'](.*)["\'].*>/miU', $codeSVG, $liens ) ) {
                  $listePaths = $paths[1];
                  $listeLiens = $liens[1];
                  for($i = 0;$i<sizeof($listePaths);$i++){
                    if(array_key_exists($i,$listeLiens)){
                      $geojson[] = [
                        "type" => "Feature",
                        "properties" => ["url"=>$listeLiens[$i]],
                        "geometry"=> [
                          "type"=> "MultiPolygon",
                          "coordinates"=>[
                          $this->decodeSvgPath( $listePaths[$i] )]
                        ]
                      ];
                    }
                  }
              } else {
                $listePaths = $paths[1];
                for($i = 0;$i<sizeof($listePaths);$i++){
                  $geojson[] = [
                    "type" => "Feature",
                    "geometry"=> [
                      "type"=> "MultiPolygon",
                      "coordinates"=>[
                      $this->decodeSvgPath( $listePaths[$i] )]
                    ]
                  ];
                }
              }
            }

            break;
          case 'convertirPath':
            if( preg_match_all( '/<path .*d=["\'](.*)["\'].*>/miU', $codeSVG, $paths ) ) {
                foreach( $paths[1] as $path ) {
                    $geojson[] = $this->decodeSvgPath( $path );
                }
            }
            break;
          case 'convertirClass':
            if( preg_match_all( '/<g .*class=["\'](.*)["\'].*>/miU', $codeSVG, $centre ) ) {
              $class = $centre[1];
              if( preg_match_all( '/<polygon .*points=["\'](.*)["\'].*>/miU', $codeSVG, $polygons ) ) {
                    $listePolygons = $polygons[1];
                    for($i = 0;$i<sizeof($listePolygons);$i++){
                      $geojson[] = [
                        "type" => "Feature",
                        "properties" => ["url"=>'','class'=>$class],
                        "geometry"=> [
                          "type"=> "MultiPolygon",
                          "coordinates"=>[
                          $this->decodeSvgPolygon( $listePolygons[$i] )]
                        ]
                      ];
                }
              }
            }
            break;
        }


        ob_flush();
        return $this->container->get('view')->render($this->response, 'result.html', [
            'title'     => "Résultat",
            'alerts'    => $this->alerts,
            'codeSVG'   => $codeSVG,
            'codeGEOJSON'  => json_encode( $geojson )
        ]);
    }

    protected function decodeSvgPolygon( $code ) {
        $points = [];
        if(!strpos(',',$code)){
          $codes = explode( ' ', $code );
          for($i = 0; $i < sizeof($codes); $i+=2){
            array_push($points, [floatval($codes[$i]),floatval($codes[$i+1])]);
          }
        } else {
          $codes = explode( ' ', $code );
          $codes = array_map( function( $c ){
              return explode( ',', $c );
          }, $codes );
          // echo '<pre>'; var_dump( $codes ); echo '</pre>';
          foreach($codes as $coordinates){
            array_push($points,[ floatval($coordinates[0]),floatval($coordinates[1])]);
          }
        }

        $points = [$points];
        // echo '<pre>'; var_dump( $points ); echo '</pre>';
        return $points;
    }

    protected function decodeSvgPath($code){
        $codes = preg_replace( '/[a-z]/i', ';$0:', $code );
        $codes = explode( ';', $codes );
        $codes = array_map( function( $c ){
            return explode( ':', $c );
        }, $codes );
        unset($codes[0]);
        $codes = array_values($codes);
        $currentP = [ 0 , 0 ];
        $firstP = [0, 0];
        $points = [];
        for($i = 0;$i < sizeof($codes);$i++){
          $currentCode = $codes[$i];
          $instruction = $currentCode[0];
          $coordonnes = $currentCode[1];
          $coordonnes = preg_replace( '/(-)/i', ';$0', $coordonnes );
          $coordonnes = preg_split( "/(;|,)/", $coordonnes );
          $codes[$i][1] = $coordonnes;
          // echo '<pre>'; var_dump( $codes[$i] ); echo '</pre>';
          echo '<pre> "INSTRUCTIONS : "'; var_dump( $instruction ); echo '</pre>';
          switch( $instruction ) {
              case 'M':
                foreach($codes[$i][1] as $key=>$coordonnates){
                  $coordonnates = floatval($coordonnates);
                  if($coordonnates == ""){
                    continue;
                  }
                  echo '<pre> "COORDONNEES : "'; var_dump( $coordonnates ); echo '</pre>';
                  if($key % 2 == 0){
                    $currentP[0] = $coordonnates;
                  } else {
                    $currentP[1] = $coordonnates;
                  }
                  array_push($points, $currentP);
                }
              break;
              case 'm' :
                foreach($codes[$i][1] as $key=>$coordonnates){
                  $coordonnates = floatval($coordonnates);
                  if($coordonnates == ""){
                    continue;
                  }
                  echo '<pre> "COORDONNEES : "'; var_dump( $coordonnates ); echo '</pre>';
                  if($key % 2 == 0){
                    $currentP[0] += $coordonnates;
                  } else {
                    $currentP[1] += $coordonnates;
                  }
                  array_push($points, $currentP);
                }
              break;
              case 'L':
                //déplacement avec tracé en absolu (x,y)
                foreach($codes[$i][1] as $key=>$coordonnates){
                  $coordonnates = floatval($coordonnates);
                  if($coordonnates == ""){
                    continue;
                  }
                  echo '<pre> "COORDONNEES : "'; var_dump( $coordonnates ); echo '</pre>';
                  if($key % 2 == 0){
                    $currentP[0] = $coordonnates;
                  } else {
                    $currentP[1] = $coordonnates;
                  }
                  array_push($points, $currentP);
                }
              break;
              case 'l':
                // déplacement avec tracé en relatif (x,y)
                foreach($codes[$i][1] as $key=>$coordonnates){
                  $coordonnates = floatval($coordonnates);
                  if($coordonnates == ""){
                    continue;
                  }
                  echo '<pre> "COORDONNEES : "'; var_dump( $coordonnates ); echo '</pre>';
                  if($key % 2 == 0){
                    $currentP[0] += $coordonnates;
                  } else {
                    $currentP[1] += $coordonnates;
                  }
                  array_push($points, $currentP);
                }
              break;
              case 'H':
                //déplacement horizontal avec tracé en absolu (x)
                foreach($codes[$i][1] as $key=>$coordonnates){
                  $coordonnates = floatval($coordonnates);
                  if($coordonnates == ""){
                    continue;
                  }
                  echo '<pre> "COORDONNEES : "'; var_dump( $coordonnates ); echo '</pre>';
                  $currentP[0] = $coordonnates;
                  array_push($points, $currentP);
                }
                break;
              case 'h':
                //déplacement horizontal avec tracé en relatif (x)
                foreach($codes[$i][1] as $key=>$coordonnates){
                  $coordonnates = floatval($coordonnates);
                  if($coordonnates == ""){
                    continue;
                  }
                  echo '<pre> "COORDONNEES : "'; var_dump( $coordonnates ); echo '</pre>';
                  $currentP[0] += $coordonnates;
                  array_push($points, $currentP);
                }
                break;
              case 'V':
                //déplacement vertical avec tracé en absolu (y)
                foreach($codes[$i][1] as $key=>$coordonnates){
                  $coordonnates = floatval($coordonnates);
                  if($coordonnates == ""){
                    continue;
                  }
                  echo '<pre> "COORDONNEES : "'; var_dump( $coordonnates ); echo '</pre>';
                  $currentP[1] = $coordonnates;
                  array_push($points, $currentP);
                }
                break;
              case 'v':
                //déplacement vertical avec tracé en relatif (y)
                foreach($codes[$i][1] as $key=>$coordonnates){
                  $coordonnates = floatval($coordonnates);
                  if($coordonnates == ""){
                    continue;
                  }
                  echo '<pre> "COORDONNEES : "'; var_dump( $coordonnates ); echo '</pre>';
                  $currentP[1] += $coordonnates;
                  array_push($points, $currentP);
                }
                break;
              case 'Z': case 'z':
                //en fin de tracé, permet de tracer le trait depuis la
                //position actuelle jusqu'au tout premier point
                foreach($codes[$i][1] as $key=>$coordonnates){
                    $currentP[0] = $firstP[0];
                    $currentP[1] = $firstP[1];
                    array_push($points, $currentP);
                }
                break;
              case 's':
                $coordonnatesOfInstruction = $codes[$i][1];
                for($j = 0;$j < sizeof($coordonnatesOfInstruction);$j++){
                  if($coordonnatesOfInstruction[$j] == ""){
                    continue;
                  }
                  $coordonnatesOfInstruction[$j] = floatval($coordonnatesOfInstruction[$j]);
                  echo '<pre> "COORDONNEES : "'; var_dump( $coordonnatesOfInstruction[$j] ); echo '</pre>';
                  if($j == 2 || ($j % 4 == 2)){
                    $currentP[0] += $coordonnatesOfInstruction[$j];
                  } else if($j == 3 || ($j % 4 == 3)){
                    $currentP[1] += $coordonnatesOfInstruction[$j];
                  }
                  array_push($points, $currentP);
                }
                break;
              case 'S':
                $coordonnatesOfInstruction = $codes[$i][1];
                for($j = 0;$j < sizeof($coordonnatesOfInstruction);$j++){
                  if($coordonnatesOfInstruction[$j] == ""){
                    continue;
                  }
                  $coordonnatesOfInstruction[$j] = floatval($coordonnatesOfInstruction[$j]);
                  echo '<pre> "COORDONNEES : "'; var_dump( $coordonnatesOfInstruction[$j] ); echo '</pre>';
                  if($j == 2 || ($j % 4 == 2)){
                    $currentP[0] = $coordonnatesOfInstruction[$j];
                  } else if($j == 3 || ($j % 4 == 3)){
                    $currentP[1] = $coordonnatesOfInstruction[$j];
                  }
                  array_push($points, $currentP);
                }
                break;
              case 'c':
                $coordonnatesOfInstruction = $codes[$i][1];
                for($j = 0;$j < sizeof($coordonnatesOfInstruction);$j++){
                  if($coordonnatesOfInstruction[$j] == ""){
                    continue;
                  }
                  $coordonnatesOfInstruction[$j] = floatval($coordonnatesOfInstruction[$j]);
                  echo '<pre> "COORDONNEES : "'; var_dump( $coordonnatesOfInstruction[$j] ); echo '</pre>';
                  if($j == 5 || ($j % 7 == 5)){
                    $currentP[0] += $coordonnatesOfInstruction[$j];
                  } else if($j == 6 || ($j % 5 == 6)){
                    $currentP[1] += $coordonnatesOfInstruction[$j];
                  }
                  array_push($points, $currentP);
                }
                break;
              case 'C':
                $coordonnatesOfInstruction = $codes[$i][1];
                for($j = 0;$j < sizeof($coordonnatesOfInstruction);$j++){
                  if($coordonnatesOfInstruction[$j] == ""){
                    continue;
                  }
                  $coordonnatesOfInstruction[$j] = floatval($coordonnatesOfInstruction[$j]);
                  echo '<pre> "COORDONNEES : "'; var_dump( $coordonnatesOfInstruction[$j] ); echo '</pre>';
                  if($j == 5 || ($j % 7 == 5)){
                    $currentP[0] = $coordonnatesOfInstruction[$j];
                  } else if($j == 6 || ($j % 5 == 6)){
                    $currentP[1] = $coordonnatesOfInstruction[$j];
                  }
                  array_push($points, $currentP);
                }
                break;
              default :
                  $this->alerts[] = [
                      'type' => 'danger',
                      'text' => "Un tracé n'a pas pu être converti"
                  ];
          }
          $points[] = $currentP;
          if($i == 0){
            $firstP[0] = $currentP[0];
            $firstP[1] = $currentP[1];
          }
        }
        // foreach($codes as $instructions){
        //   $instructions[1] = explode( ',', $instructions[1] );
        //   // echo '<pre>'; var_dump( $codes ); echo '</pre>';
        // }
        // echo '<pre>'; var_dump( $codes ); echo '</pre>';
        $points = [$points];
        return $points;
    }

    protected function decodeSvgPathOLD( $code ) {
      // echo '<pre>'; var_dump( $code ); echo '</pre>';
        $codeChaine = preg_replace( '/[a-z]/i', ';$0:', $code );
        $codes = explode( ';', $codeChaine );
        $codes = array_map( function( $c ){
            return explode( ':', $c );
        }, $codes );
         // echo '<pre>'; var_dump( $codes ); echo '</pre>';
        $points = [];
        $currentP = [ 0, 0 ];
        foreach( $codes  as $instruction ) {
          // echo '<pre>'; var_dump( $instruction ); echo '</pre>';
            $values = [];
            if( count( $instruction ) === 2 ) {
                if( preg_match_all( '/[-+]?[0-9]*\.?[0-9]*/', $instruction[1], $values ) ) {
                    // var_dump( $values );
                    echo '<pre>'; var_dump($instruction[0]) ; var_dump( $values ); echo '</pre>';
                    switch( $instruction[0] ) {
                        case 'M':
                          // déplacement sans tracé en absolu (x,y)
                          $currentP[ 0 ] = $values[ 0 ][ 0 ];
                          $currentP[ 1 ] = $values[ 0 ][ 1 ];
                        break;
                        case 'm' :
                          // déplacement sans tracé en relatif (x,y)
                          $currentP[ 0 ] += $values[ 0 ];
                          $currentP[ 1 ] += $values[ 1 ];
                        break;
                        case 'L':
                          //déplacement avec tracé en absolu (x,y)
                        break;
                        case 'l':
                          // déplacement avec tracé en relatif (x,y)
                        break;
                        case 'H':
                          //déplacement horizontal avec tracé en absolu (x)
                          break;
                        case 'h':
                          //déplacement horizontal avec tracé en relatif (x)
                          break;
                        case 'V':
                          //déplacement vertical avec tracé en absolu (y)
                          break;
                        case 'v':
                          //déplacement vertical avec tracé en relatif (y)
                          break;
                        case 'Z': case 'z':
                          //en fin de tracé, permet de tracer le trait depuis la
                          //position actuelle jusqu'au tout premier point
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
