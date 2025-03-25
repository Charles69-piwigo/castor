<?php
/*
Plugin Name: castor
Version: 3.1
Description: Castor - diffuser vos photos et vidéos sur une télé android ou via un chromecast
Plugin URI: 
Author: Charles69
Author URI: 
*/


 // ============  VERSIONS =========================================================
 // historique des versions
 /*

  version 3.1 - 25/03/2025 (diffusé)
  changement numérotation des versions - nettoyage du code
  ajouté le pilotage des vidéos Play on/off - avancement - Son on/off - Volume 
  css modifié prise en compte du thème bootstrap-darkroom & modus

  version 2.0.3 - 12/03/2025
  modifié imagemagick - correction calcul du redimensionnement avec respect du ratio
  conservation des miniatures -xl  1224x918 pixels

	version 2.0.2 - 10/03/2025 (diffusé)
	supprimé création rep dans _data/i/galleries
 
	version 2.0.1 - 07/03/2025
	modifié le calcul du mime type
	modifié le calcul des dimensions réduites , problème reSize aléatoire

	version 2.0.0 - 02/03/2025 
	gain de vitesse substantiel
	envoi d'une version réduite de la photo au lieu de la photo originale
  
 */

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

if (basename(dirname(__FILE__)) != 'castor')
{
  add_event_handler('init', 'castor_error');
  function castor_error()
  {
    global $page;
    $page['errors'][] = 'le nom du répertoire castor est incorrect, désactiver le plugin et le renommer  "castor"';
  }
  return;
}

// +-----------------------------------------------------------------------+
// | Define plugin constants                                               |
// +-----------------------------------------------------------------------+

define('CAST_DIR',    basename(dirname(__FILE__)));
define('CAST_PATH' , PHPWG_PLUGINS_PATH . CAST_DIR . '/');

//echo ' CAST_PATH= ' , CAST_PATH ;
//echo ' CAST_DIR= ' , CAST_DIR ;

// SCRIPT =======================================================================================
// ajoute les scripts Google js Cast.js et css

add_event_handler('loc_begin_page_tail', 'cast_add_api');
function cast_add_api()
{
    global $page, $template;
    //print_r($page) ;
    
    $template->append('footer_elements', '
    <script src="https://www.gstatic.com/cv/js/sender/v1/cast_sender.js"></script>
    <script src="plugins/castor/cast.js"></script>
    <link rel="stylesheet" href="plugins/castor/css/cast.css" />
    ');
    
}

// =========================================================================================
// MENU PRINCIPAL - Ajout du bouton de cast

add_event_handler('blockmanager_register_blocks', 'register_cast_menubar_blocks');

function register_cast_menubar_blocks( $menu_ref_arr )
{
  $menu = & $menu_ref_arr[0];
  if ($menu->get_id() != 'menubar')
    return;
  $menu->register_block( new RegisteredBlock( 'mbCast', 'Cast', 'CAST'));
}

add_event_handler('blockmanager_apply', 'cast_apply');

function cast_apply($menu_ref_arr)
{
  global $template,$user;

  $menu = & $menu_ref_arr[0];
 
  if (($block = $menu->get_block( 'mbCast' )) != null) {  
  //echo 'guetblock ok bouton ajouté ' ;

    $template->set_template_dir(CAST_PATH.'template/');
		if ($user['theme'] == 'bootstrapdefault'||$user['theme'] == 'bootstrap_darkroom'){
		  $block->template = 'menubar_cast_Bootstrap.tpl';
          //echo 'menu boostrap' ;		
		}else{
		  $block->template = 'menubar_cast.tpl';
		}
} 
  //else { echo 'getblock échoué';} 
}
//-----------------------------------------------------------------------



//===================================================================================
//   IDENTIFICATION PHOTO/VIDEO - Récupération de l'URL et du MIME
/// on est dans une page qui contient une photo  ou une video * loc_end_picture *

//==================================================================================
// Récupération de l'URL et du MIME TYPE
add_event_handler('loc_end_picture','calc_url') ; 

function calc_url() {

  //echo('picture');
  
  global $template, $picture;
    
    if (!isset($picture['current']['path'])) {
        return;
    }

    // Récupérer l'URL de base  ==============
    $url_base = get_absolute_root_url();            // renvoie https://robert .. /photo.charles/
    //echo $url_base ;
    $url_picture = $picture['current']['path'];     // débute par ./
    $url_picture2 = substr($url_picture,2);         // enlève ./ au début
    $url_original = $url_base . $url_picture2;      // url https de la photo originale
    //echo '  url orig= ' , $url_original ;
    
    // Récupération du Mime Type ==============
    //echo gettype($url_picture) ;
    $mime_type = mime_content_type($url_picture) ;
    //echo 'mime type= ' , $mime_type;

    //if (substr($url_original,-3) !== 'mp4') {    // ce n'est pas une vidéo
    if ($mime_type !== 'video/mp4') {    // ce n'est pas une vidéo

            $url_reduit = construire_url_reduit($url_picture2) ;   // url local de la photo réduite
            //echo '  url réduit=  ',$url_reduit ;
            if (file_exists($url_reduit)) {  
                //echo "Le fichier réduit local existe." , $chemin_reduit; // on ne fait rien
            } else {
                //echo "Le fichier réduit local n'existe pas."; // on le crée
                $url_local = generer_jpeg_reduit($url_picture, $url_reduit, 1224, 918) ; // imagemagick redimensionnement en -xl Très Grand
                //echo '  url créée= ', $url_local ;
            }

            $url_reduit = substr($url_reduit,2) ;     // url locale , on enlève ./  
            //echo "reduit reduit= " , $url_reduit ;
            $url_local = $url_base . $url_reduit ;    // url https de la photo réduite 
            //echo 'url locale= ', $url_local ;

            // Assigner à Smarty
            //$url_original = json_encode($url_original);
            $template->assign('IMG_URL', $url_local); // Assignation dans le template
            $template->assign('MIME_TYPE', $mime_type);  // assignation dans le template
    } else {
            // CAS DES VIDEOS - on passe la photo de pwg_representative et le plugin JS VIDEO fait le job !!      
            // Assigner à Smarty
            //$url_original = json_encode($url_original);
            //echo 'vidéo' ;
            $template->assign('IMG_URL', $url_original); // Assignation dans le template
            $template->assign('MIME_TYPE', $mime_type);  // assignation dans le template
    }      
  }

//---------------------------------------------------------------------------------
// Création du nom du chemin attendu du jpg réduit - exemple.jpg --> exemple-xl.jpg
function construire_url_reduit($url_pict) {
  // Insérer '_data/i/' entre $url_base et $url_picture
  $url_local = './_data/i/' . $url_pict ; // pas de https pour imagick
  // Remplacer 'exemple.jpg' par 'exemple-xl.jpg'
 // $url_locale = preg_replace('/(.*\/)([^\/]+)\.jpg$/i', '$1$2-xl.jpg', $url_locale);
  $url_local = preg_replace('/^(.*\/)?([^\/]+?)\.jpe?g$/i', '$1$2-xl.jpg', $url_local);   /// ici on définit le suffixe du jpg
  return $url_local;
}

//-------------------------------------------------------------------------------
//Création du jpg réduit à l'endroit et avec le nom attendu
function generer_jpeg_reduit($chemin_source, $chemin_destination, $larg_cible, $haut_cible) {

  try {

      // Charger l'image avec Imagick
      $image = new Imagick($chemin_source);
      if (!$image) { 
        echo "Imagick non dispo";
      }

      // Vérifier que c'est bien un JPEG
      if ($image->getImageFormat() !== 'JPEG') {
        //echo "pas un jpeg";
          return false;
      } 

      // calcul des dimensions pour éviter distorsion aléatoire
      $haut_orig = $image->getImageHeight();
      $larg_orig = $image->getImageWidth();
      $img_ratio = $larg_orig / $haut_orig ;
      
            $largeur = round(min($larg_cible,$haut_cible * $img_ratio)) ;
            $hauteur = round(min($haut_cible,$larg_cible / $img_ratio)) ;

            //echo 'larg= ' , $larg_orig , ' haut= ' , $haut_orig ;
            //echo 'larg= ' , $largeur , ' haut= ' , $hauteur ;

      // Redimensionner l'image avec une haute qualité
      $image->resizeImage($largeur, $hauteur, Imagick::FILTER_LANCZOS, 1,true);

      // Définir le format et la qualité JPEG
      $image->setImageFormat('jpeg');
      $image->setImageCompression(Imagick::COMPRESSION_JPEG);
      $image->setImageCompressionQuality(95);

      // Sauvegarder l'image redimensionnée
      $image->writeImage($chemin_destination);

      // Libérer la mémoire
      $image->clear();
      $image->destroy();
      //echo "image créée avec succès ";

      return $chemin_destination;

  } catch (Exception $e) {
      //echo "exception" ;
      //die("Erreur Imagick : ". $e->getMessage()) ;
      return false;
  }
}

// ==========================================================================================
// Création des variables JS   JSUrl et JSMime à partir des assignations

add_event_handler('loc_end_picture', 'castAddVariables');

function castAddVariables()  {
    global $page, $template;
    
    // Charger le template
    $template->set_filenames(array('cast_footer' => realpath(PHPWG_PLUGINS_PATH . 'castor/template/cast_var.tpl')));

    // Capturer le rendu du template
    ob_start();
    $template->parse('cast_footer');
    $footer_content = ob_get_clean(); // Capture le HTML généré

    // Ajouter ce contenu dans footer_elements
    $template->append('footer_elements', $footer_content);
}

// -----------------------------------------------------------------------------------------------

// Ajout d'un ensemble de boutons en dessous de la photo/vidéo

add_event_handler('loc_begin_picture', 'ajouter_videocontrol');

function ajouter_videocontrol() {
    global $template;
    $template->set_filename('videocontrol', CAST_PATH.'template/video_control.tpl');
    $template->assign('MON_BT', $template->parse('videocontrol', true));
    // Enregistrement du pré-filtre pour modifier le template
    $template->set_prefilter('picture', 'addButton');
}


function addButton($content) {
    $search = '{$ELEMENT_CONTENT}';  // éléments Image dans picture.tpl
    $replacement = '{$ELEMENT_CONTENT}{$MON_BT}';  // place le bouton sous l'image
    return str_replace($search, $replacement, $content);
}

?>
