# Castor — diffuser photos et vidéos sur une TV Android ou un Chromecast

Plugin Piwigo : une icône Cast apparaît dans la barre d'actions d'une photo ou d'une vidéo.
Pendant la diffusion, une icône d'état bleue s'affiche dans la barre de menu des albums
(un clic l'arrête) et une télécommande (lecture, position, son) accompagne les vidéos.

## Utilisation avec Google Chrome

Chrome a modifié son interface de cast : le bouton du plugin ouvre la fenêtre des
**commandes multimédia** de Chrome, et non plus la petite fenêtre « Caster ».

1. Cliquez sur l'icône Cast du plugin.
2. Dans la fenêtre qui s'ouvre, la section **« Caster sur un appareil »** liste vos TV et Chromecast.
   Cliquez sur l'appareil voulu.
3. Si cette section n'apparaît pas (premier usage, appareil encore inconnu de Chrome) :
   ouvrez le menu de Chrome (⋮) > **Caster, enregistrer et partager > Caster…**, choisissez votre TV une fois,
   puis refermez. Les fois suivantes, l'appareil est proposé directement par le bouton du plugin.
4. Si la TV propose une mise à jour du Chromecast, acceptez-la : une version trop ancienne
   n'apparaît plus dans la liste.

Avec d'autres navigateurs (Opera, Edge...), le bouton ouvre directement la fenêtre « Caster ».

## Vidéos

- Formats lus par un Chromecast : MP4 (H.264) et WebM. Un fichier H.265/HEVC n'est pas lu par tous les modèles.
- Si le plugin **private_derivative_protection** est actif, utilisez sa version 1.5 ou plus récente :
  elle ajoute l'en-tête CORS nécessaire à la lecture des vidéos par la TV.
- Les vidéos des albums privés ne peuvent pas être diffusées : la TV n'a pas votre session.

## Dépannage

- Rien ne se passe : vérifiez que l'ordinateur et la TV sont sur le même réseau.
- La TV affiche « Default Media Receiver » sans lire la vidéo : fichier illisible par la TV, album privé ou
  en-tête CORS absent côté serveur.
- Après une mise à jour du plugin, un message en haut de la page indique la cause de l'échec.
  Pour un diagnostic détaillé, décommentez les lignes `//*console.log` de `cast.js`.
