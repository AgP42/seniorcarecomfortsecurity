<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class seniorcarecomfortsecurity extends eqLogic {
    /*     * *************************Attributs****************************** */



    /*     * ***********************Methode static*************************** */

    public static function sensorSecurity($_option) { // fct appelée par le listener des capteurs de sécurité, n'importe quel capteur arrive ici


      $seniorcarecomfortsecurity = seniorcarecomfortsecurity::byId($_option['seniorcarecomfortsecurity_id']); // on cherche la personne correspondant au bouton d'alerte

      // on cherche quel est l'event qui nous a déclenché pour pouvoir chopper son nom et type (utilisé pour les tags) et sa valeur (pour gestion de la repetition)
      foreach ($seniorcarecomfortsecurity->getConfiguration('security') as $security) { // on boucle direct dans la conf
        if ('#' . $_option['event_id'] . '#' == $security['cmd']) {

          log::add('seniorcarecomfortsecurity', 'debug', $seniorcarecomfortsecurity->getHumanName() . '################ Detection d\'un trigger de sécurité ############ - nom : ' . $security['name'] . ' - cmd : ' . $security['cmd']  . ' - type : ' . $security['sensor_security_type'] . ' - valeur : ' . $_option['value']);

          if ($seniorcarecomfortsecurity->getCache('sensor_' . $_option['event_id']) != $_option['value']){ // si notre valeur a changé, donc n'est pas une répétition

            if (isset($security['invert']) && $security['invert'] == 1) { // si la case "inverser" est cochée, on inverse la valeur
              $_option['value'] = ($_option['value'] == 0) ? 1 : 0;
            }

            if ($_option['value'] == 1) {

              log::add('seniorcarecomfortsecurity', 'info', $seniorcarecomfortsecurity->getHumanName() . ' - Capteurs securité à prendre en compte, nom : ' . $security['name'] . ' - cmd : ' . $security['cmd']  . ' - type : ' . $security['sensor_security_type']);

              $seniorcarecomfortsecurity->execActions('action_security', $security['name'], $security['sensor_security_type']); // on appelle les actions definies pour cette personne

            }

          } // fin if notre valeur est nouvelle

          $seniorcarecomfortsecurity->setCache('sensor_' . $_option['event_id'], $_option['value']); //on garde en cache la valeur actuelle du capteur
          log::add('seniorcarecomfortsecurity', 'debug', $seniorcarecomfortsecurity->getHumanName() . ' - cache *sensor_' . $_option['event_id'] . '* : ' . $seniorcarecomfortsecurity->getCache('sensor_' . $_option['event_id']));

        } // fin if on est sur le capteur qui nous a déclenché
      } // fin foreach tous les capteurs security de la conf
    } // fin de la fonction appellée par le listener

    public static function sensorSecurityCancel($_option) { // fct appelée par le listener des boutons d'annulation de l'alerte de sécurité, n'importe quel bouton arrive ici


      $seniorcarecomfortsecurity = seniorcarecomfortsecurity::byId($_option['seniorcarecomfortsecurity_id']); // on cherche la personne correspondant au bouton d'alerte
  //    log::add('seniorcarecomfortsecurity', 'debug', '################ Detection d\'un bouton d\'annulation d\'alerte de sécurité ############');
      log::add('seniorcarecomfortsecurity', 'info', $seniorcarecomfortsecurity->getHumanName() . ' - Bouton annulation de l\'alerte de sécurité détecté');

      $seniorcarecomfortsecurity->execActions('action_cancel_security'); // on appelle les actions definies pour cette personne

    }

    public static function checkAndActionSeuilsSensorConfort($seniorcarecomfortsecurity, $_name, $_cmd, $_seuilBas, $_seuilHaut, $_type) { // appelée soit par le cron15, soit par un listener (via la fct sensorConfort - desactivée), va regarder si on est dans les seuils définis et si non appliquer les actions voulues

    // TODO on pourrait ajouter une durée min pendant laquelle le capteur est hors seuils avant de déclencher l'alerte
    // TODO on pourrait ajouter la date de collecte de la valeur pour ne pas faire des alertes sur une vieille info, ou au contraire ajouter une alerte si pas de valeur fraiche pendant un certain temps. Mais ça peut etre aussi géré par le core dans les configuration de la cmd...
	  log::add('seniorcarecomfortsecurity', 'debug', 'Fct checkAndActionSeuilsSensorConfort');
      $now = time();
      $rep_warning = $seniorcarecomfortsecurity->getConfiguration('repetition_warning');
      $tempsDepuisActionWarningConfort = $now - intval($seniorcarecomfortsecurity->getCache('actionWarningConfortStartTimestamp' . $_cmd)); // on garde 1 cache par cmd
      $warningConfortLauched = $seniorcarecomfortsecurity->getCache('WarningConfortLauched' . $_cmd);

      $valeur = jeedom::evaluateExpression($_cmd);

      log::add('seniorcarecomfortsecurity', 'debug', 'Fct checkAndActionSeuilsSensorConfort, name : ' . $_name . ' - ' . $_type . ' - ' . $_cmd . ' - ' . $valeur . ' - ' . $_seuilBas . ' - ' . $_seuilHaut . ' - Rep warning : ' . $seniorcarecomfortsecurity->getConfiguration('repetition_warning'));

      log::add('seniorcarecomfortsecurity', 'debug', 'Fct checkAndActionSeuilsSensorConfort, WarningConfortLauched : ' . $warningConfortLauched . ' - last action lancé il y a (min) : ' . $tempsDepuisActionWarningConfort / 60);

      if(!is_numeric($valeur)){

        log::add('seniorcarecomfortsecurity', 'debug', 'Capteurs confort :' . $_name . ' la valeur est pas numerique, on fait rien !');

      } else if (($valeur <= $_seuilHaut && $valeur >= $_seuilBas) && !$warningConfortLauched){ // on est dans les seuils et on a pas lancé notre warning : aucune action a lancer

        log::add('seniorcarecomfortsecurity', 'debug', 'Capteurs confort :' . $_name . ' dans les seuils, on fait rien');
        return 1; // on retourne que notre capteur est ok dans les seuils

      } else if (($valeur <= $_seuilHaut && $valeur >= $_seuilBas) && $warningConfortLauched){ // on est dans les seuils et on a précédemment lancé notre warning => actions de retour à la normal

        log::add('seniorcarecomfortsecurity', 'info', $seniorcarecomfortsecurity->getHumanName() . ' - Capteur confort : '. $_type . ' - ' . $_name . ' de retour dans les seuils - valeur : ' . $valeur . ' - seuils : ' . $_seuilBas . ' - ' . $_seuilHaut);
        $seniorcarecomfortsecurity->setCache('WarningConfortLauched' . $_cmd, false); // on remet dans le cache qu'on a pas lancé les actions
        $seniorcarecomfortsecurity->execActions('action_cancel_warning_confort', $_name, $_type, $valeur, $_seuilBas, $_seuilHaut); // appel de la boucle d'execution des actions avec les infos pour les tag des messages
        return 1; // on retourne que notre capteur est ok dans les seuils

      } else if (($valeur > $_seuilHaut || $valeur < $_seuilBas) && // si la valeur sort des seuils et selon le choix de repetition
         ($rep_warning == '' ||
         ($rep_warning == '15min' &&  $tempsDepuisActionWarningConfort >= 60*14) || // si on a pas defini la repetition de warning ou si defini sur "15min" ou si
         ($rep_warning == 'once' && !$warningConfortLauched) || // rep_warning est sur "1fois" et qu'on ne l'a pas encore lancé ou si
         ($rep_warning == '1hour' && $tempsDepuisActionWarningConfort >= 60*59) || // rep_warning sur 1h et dernier lancement depuis plus de 59min (pour éviter de tomber 1s apres 1h et donc de louper le rappel...)
         ($rep_warning == '6hours' && $tempsDepuisActionWarningConfort >= 60*59*6) // rep_warning sur 6h et dernier lancement depuis 6h-6min
        )){

        log::add('seniorcarecomfortsecurity', 'info', $seniorcarecomfortsecurity->getHumanName() . ' - Capteur confort : '. $_type . ' - ' . $_name . ' est hors seuils - valeur : ' . $valeur . ' - seuils : ' . $_seuilBas . ' - ' . $_seuilHaut . ', temps depuis le lancement de la précédente alerte : ' . $tempsDepuisActionWarningConfort/60 . 'min, alerte déjà lancée : ' . $warningConfortLauched . ' répétition voulue : ' . $rep_warning);

        $seniorcarecomfortsecurity->setCache('WarningConfortLauched' . $_cmd, true); // on garde en cache qu'on a lancé nos actions au moins 1 fois pour cette commande
        $seniorcarecomfortsecurity->setCache('actionWarningConfortStartTimestamp' . $_cmd, $now); // on memorise l'heure du lancement du warning

        $seniorcarecomfortsecurity->execActions('action_warning_confort', $_name, $_type, $valeur, $_seuilBas, $_seuilHaut); // on execute toutes les actions

        return 0; // on retourne qu'on a 1 capteur hors seuil (ils doivent tous répondre 1 pour que le cron lance les actions "tous ok")

      } else { // on est pas dans les seuils mais on a deja lancé les alertes selon la repetition voulu

        log::add('seniorcarecomfortsecurity', 'debug', 'Capteurs confort :' . $_name . ' est hors seuils, mais déjà lancé les actions de warning');
        return 0; // on retourne qu'on a 1 capteur hors seuil

      } //*/

    }

// commenté car on utilise plus les listener pour les confort, juste le cron 15
/*    public static function sensorConfort($_option) { // fct appelée par le listener des capteurs conforts (on sait pas lequel, ça serait trop simple, mais on connait l'event_id et la valeur).

      log::add('seniorcarecomfortsecurity', 'debug', '################ Detection d\'un changement d\'un capteur confort ############');

    //  log::add('seniorcarecomfortsecurity', 'debug', 'Fct sensorConfort appelé par le listener : $_option[seniorcarecomfortsecurity_id] : ' . $_option['seniorcarecomfortsecurity_id'] . ' - value : ' . $_option['value'] . ' - event_id : ' . $_option['event_id']);

      $seniorcarecomfortsecurity = seniorcarecomfortsecurity::byId($_option['seniorcarecomfortsecurity_id']);
      if (is_object($seniorcarecomfortsecurity) && $seniorcarecomfortsecurity->getIsEnable() == 1 ) {
        foreach ($seniorcarecomfortsecurity->getConfiguration('confort') as $confort) { // on boucle direct dans la conf
          if ('#' . $_option['event_id'] . '#' == $confort['cmd']) { // on cherche quel est l'event qui nous a déclenché

          //  log::add('seniorcarecomfortsecurity', 'debug', 'Fct sensorConfort appelé par le listener, name : ' . $confort['name'] . ' - cmd : ' . $confort['cmd']  . ' - ' . $confort['sensor_confort_type'] . ' - ' . $confort['seuilBas'] . ' - ' . $confort['seuilHaut']);

            if($confort['seuilBas'] != '' || $confort['seuilHaut'] != '') { // si les seuils sont definis (on set le listener de toutes facons maintenant)
              $seniorcarecomfortsecurity->checkAndActionSeuilsSensorConfort($seniorcarecomfortsecurity, $confort['name'], $_option['value'], $confort['seuilBas'], $confort['seuilHaut'], $confort['sensor_confort_type']);
            }

          }

        }
      }

    } //*/

    //*
    // * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
    // Sert ici pour les capteurs conforts
      public static function cron15() {

        log::add('seniorcarecomfortsecurity', 'debug', '#################### CRON 15 ###################');

        //pour chaque equipement (personne) declaré par l'utilisateur
        foreach (self::byType('seniorcarecomfortsecurity',true) as $seniorcarecomfortsecurity) {

          if (is_object($seniorcarecomfortsecurity) && $seniorcarecomfortsecurity->getIsEnable() == 1) { // si notre eq existe et est actif

            $etatSensor = 1;
            foreach ($seniorcarecomfortsecurity->getConfiguration('confort') as $confort) { // on boucle direct dans la conf

              log::add('seniorcarecomfortsecurity', 'debug', 'Cron15 boucle capteurs confort, name : ' . $confort['name'] . ' - cmd : ' . $confort['cmd']  . ' - ' . $confort['sensor_confort_type'] . ' - ' . print_r($confort['seuilBas'],true) . ' - ' . print_r($confort['seuilHaut'],true));

			  if (is_numeric($confort['seuilBas']) && is_numeric($confort['seuilHaut'])) {
              //if($confort['seuilBas'] != '' || $confort['seuilHaut'] != '') { // évalue si on a au moins 1 seuil defini (de toute facon on peut pas n'en remplir qu'1 des deux)

                $result = $seniorcarecomfortsecurity->checkAndActionSeuilsSensorConfort($seniorcarecomfortsecurity, $confort['name'], $confort['cmd'], $confort['seuilBas'], $confort['seuilHaut'], $confort['sensor_confort_type']); // on appelle la fct d'évaluation et on fait le produit du retour de chacun
                // il suffit qu'il y ai 1 capteur qui renvoie 0 pour que notre $etatSensor passe a 0
				if (is_numeric($result)) {
				    $etatSensor *= (int)$result;
				} else {
				    log::add('seniorcarecomfortsecurity', 'error', 'checkAndActionSeuilsSensorConfort a retourné une valeur non valide : ' . print_r($result, true));
				}

                log::add('seniorcarecomfortsecurity', 'debug', 'Cron15 boucle capteurs confort, etatSensor : ' . $etatSensor);
              } else {
				log::add('seniorcarecomfortsecurity', 'error', 'Seuils invalides détectés dans la configuration : ' . print_r($confort, true));
			  }

            } // fin foreach tous les capteurs conforts de la conf

            if($etatSensor){ // ils ont tous repondu 1, on va lancer les actions
              $seniorcarecomfortsecurity->execActions('action_cancel_all_warning_confort'); // appel de la boucle d'execution des actions avec les infos pour les tag des messages
            }

          } // fin if eq actif

        } // fin foreach equipement

      } //*/

    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDaily() {

      }
     */

	 private function getArrayConfiguration($key) {
	     $value = $this->getConfiguration($key);
	     if (is_string($value)) {
	         $decoded = json_decode($value, true);
	         if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
	             return $decoded;
	         }
	     }
	     return is_array($value) ? $value : [];
	 }


    public function execActions($_config, $_sensor_name = NULL, $_sensor_type = NULL, $_sensor_value = NULL, $_seuilBas = NULL, $_seuilHaut = NULL) { // on donne le type d'action en argument et ca nous execute toute la liste. Les autres arguments sont pour les tag des messages si applicable

      log::add('seniorcarecomfortsecurity', 'info', $this->getHumanName() . ' - ################ Execution des actions du type ' . $_config . ' pour ' . $this->getName() .  ' ############');

      foreach ($this->getArrayConfiguration($_config) as $action) { // on boucle pour executer toutes les actions définies
        try {
          $options = array(); // va permettre d'appeler les options de configuration des actions, par exemple un scenario un message
          if (isset($action['options'])) {
            $options = $action['options'];
            foreach ($options as $key => $value) { // ici on peut définir les "tag" de configuration qui seront à remplacer par des variables
              // str_replace ($search, $replace, $subject) retourne une chaîne ou un tableau, dont toutes les occurrences de search dans subject ont été remplacées par replace.
              $value = str_replace('#senior_name#', $this->getConfiguration('senior_name'), $value);
              $value = str_replace('#senior_phone#', $this->getConfiguration('senior_phone'), $value);
              $value = str_replace('#senior_address#', $this->getConfiguration('senior_address'), $value);

              $value = str_replace('#trusted_person_name#', $this->getConfiguration('trusted_person_name'), $value);
              $value = str_replace('#trusted_person_phone#', $this->getConfiguration('trusted_person_phone'), $value);

              $value = str_replace('#sensor_name#', $_sensor_name, $value);
              $value = str_replace('#sensor_type#', $_sensor_type, $value);
              $value = str_replace('#sensor_value#', $_sensor_value, $value);
              $value = str_replace('#low_threshold#', $_seuilBas, $value);
              switch ($_sensor_type) {
                  case 'temperature':
                      $unit = '°C';
                      break;
                  case 'humidity':
                      $unit = '%';
                      break;
                  case 'co2':
                      $unit = 'ppm';
                      break;
                  default:
                      $unit = '';
                      break;
              }
              $value = str_replace('#unit#', $unit, $value);
              $options[$key] = str_replace('#high_threshold#', $_seuilHaut, $value);
            }
          }
          scenarioExpression::createAndExec('action', $action['cmd'], $options);
        } catch (Exception $e) {
          log::add('seniorcarecomfortsecurity', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
        }
      } //*/

    }

    /*     * *********************Méthodes d'instance************************* */

    public function cleanAllListener() {

      log::add('seniorcarecomfortsecurity', 'debug', 'Fct cleanAllListener pour : ' . $this->getName());

      $listeners = listener::byClass('seniorcarecomfortsecurity'); // on prend tous nos listeners de ce plugin, pour toutes les personnes
      foreach ($listeners as $listener) {
        $seniorcarecomfortsecurity_id_listener = $listener->getOption()['seniorcarecomfortsecurity_id'];

    //    log::add('seniorcarecomfortsecurity', 'debug', 'cleanAllListener id lue : ' . $seniorcarecomfortsecurity_id_listener . ' et nous on est l id : ' . $this->getId());

        if($seniorcarecomfortsecurity_id_listener == $this->getId()){ // si on correspond a la bonne personne, on le vire
          $listener->remove();
        }

      }

    }

    public function preInsert() {

    }

    public function postInsert() {

    }

    public function preSave() {

    }

    // fct appellée par Jeedom aprés l'enregistrement de la configuration
    public function postSave() {


      //########## 1 - On va lire la configuration des capteurs dans le JS et on la stocke dans un grand tableau #########//

      $jsSensors = array(
        'confort' => array(), // idem capteurs conforts
        'security' => array(), // idem capteurs sécurité
        'cancel_security' => array(), // boutons d'annulation alerte sécurité
      );

      foreach ($jsSensors as $key => $jsSensor) { // on boucle dans tous nos types de capteurs pour recuperer les infos
        log::add('seniorcarecomfortsecurity', 'debug', 'Boucle de $jsSensors : key : ' . $key);

        if (is_array($this->getConfiguration($key))) {
          foreach ($this->getConfiguration($key) as $sensor) {
            if ($sensor['name'] != '' && $sensor['cmd'] != '') { // si le nom et la cmd sont remplis

              $jsSensors[$key][$sensor['name']] = $sensor; // on stocke toute la conf, c'est à dire tout ce qui dans notre js avait la class "expressionAttr". Pour retrouver notre champs exact : $jsSensors[$key][$sensor['name']][data-l1key]. // attention ici a ne pas remplacer $jsSensors[$key] par $jsSensor. C'est bien dans le tableau d'origine qu'on veut écrire, pas dans la variable qui le represente dans cette boucle
              log::add('seniorcarecomfortsecurity', 'debug', 'Capteurs sensor config lue : ' . $sensor['name'] . ' - ' . $sensor['cmd']);

            }
          }
        }
      }

      //########## 2 - On boucle dans toutes les cmd existantes, pour les modifier si besoin #########//


      foreach ($jsSensors as $key => $jsSensor) { // on boucle dans tous nos différents types de capteurs. $key va prendre les valeurs suivantes : life_sign, alert_bt, confort puis security

        foreach ($this->getCmd() as $cmd) {
          if ($cmd->getLogicalId() == 'sensor_' . $key) {
            if (isset($jsSensor[$cmd->getName()])) { // on regarde si le nom correspond à un nom dans le tableau qu'on vient de recuperer du JS, si oui, on actualise les infos qui pourraient avoir bougé

              $sensor = $jsSensor[$cmd->getName()];
              $cmd->setValue($sensor['cmd']);

              if(isset($sensor['sensor_'.$key.'_type'])){ // ce sera vrai pour les types life-sign, confort et security
                $cmd->setGeneric_type($sensor['sensor_'.$key.'_type']);
              }

              if($key == 'confort'){ // uniquement pour confort

              //  $cmd->setConfiguration('minValue', $sensor['seuilBas']);
              //  $cmd->setConfiguration('maxValue', $sensor['seuilHaut']);
                switch ($sensor['sensor_confort_type']) {
                    case 'temperature':
                        $unit = '°C';
                        break;
                    case 'humidity':
                        $unit = '%';
                        break;
                    case 'co2':
                        $unit = 'ppm';
                        break;
                    default:
                        $unit = '-';
                        break;
                }
                $cmd->setUnite($unit);

              } //*/

              $cmd->save();

      //        log::add('seniorcarecomfortsecurity', 'debug', 'apres if update confort : , $sensor[seuilBas] : ' . $sensor['seuilBas'] . ', $cmd->getConfiguration(minValue) : ' . $cmd->getConfiguration('minValue'));

              // va chopper la valeur de la commande puis la suivre a chaque changement
			  $value = $cmd->execCmd();
			  if (!is_numeric($value) || is_nan((float)$value) || $value == '') {
              //if (is_nan($cmd->execCmd()) || $cmd->execCmd() == '') {
                $cmd->setCollectDate('');
                $cmd->event($cmd->execute());
              }

              unset($jsSensors[$key][$cmd->getName()]); // on a traité notre ligne, on la vire. Attention ici a ne pas remplacer $jsSensors[$key] par $jsSensor. C'est bien dans le tableau d'origine qu'on veut virer notre ligne

            } else { // on a un sensor qui était dans la DB mais dont le nom n'est plus dans notre JS : on la supprime ! Attention, si on a juste changé le nom, on va le supprimer et le recreer, donc perdre l'historique éventuel.
              $cmd->remove();
            }
          }
        } // fin foreach toutes les cmd du plugin
      } // fin foreach nos differents types de capteurs//*/

      //########## 3 - Maintenant on va creer les cmd nouvelles de notre conf (= celles qui restent dans notre tableau) #########//

      foreach ($jsSensors as $key => $jsSensor) { // on boucle dans tous nos types de capteurs. $key va prendre les valeurs suivantes : life_sign, alert_bt, confort puis security

        foreach ($jsSensor as $sensor) { // pour chacun des capteurs de ce type

          // ce qui identifie d'un point de vu unique notre capteur c'est son type et sa value(cmd)

          log::add('seniorcarecomfortsecurity', 'debug', 'New Capteurs config : type : ' . $key . ', sensor name : ' . $sensor['name'] . ', sensor cmd : ' . $sensor['cmd']);

          $cmd = new seniorcarecomfortsecurityCmd();
          $cmd->setEqLogic_id($this->getId());
          $cmd->setLogicalId('sensor_' . $key);
          $cmd->setName($sensor['name']);
          $cmd->setValue($sensor['cmd']);
          $cmd->setType('info');
          $cmd->setSubType('numeric');
          $cmd->setIsVisible(0);
          $cmd->setIsHistorized(1);
          $cmd->setConfiguration('historizeMode', 'none');

          if(isset($sensor['sensor_'.$key.'_type'])){ // ce sera vrai pour les types confort et security, mais pas annulation security
            $cmd->setGeneric_type($sensor['sensor_'.$key.'_type']);
          }

          if($key == 'confort'){ // uniquement pour les commandes de types confort

        //    $cmd->setConfiguration('minValue', $sensor['seuilBas']);
        //    $cmd->setConfiguration('maxValue', $sensor['seuilHaut']);
            switch ($sensor['sensor_confort_type']) {
                case 'temperature':
                    $unit = '°C';
                    break;
                case 'humidity':
                    $unit = '%';
                    break;
                case 'co2':
                    $unit = 'ppm'; //Les sondes de CO2 présentent généralement une plage de mesure de 0-5000 ppm. Il faudra recommander dans la doc une alerte à partir de 1000ppm max
                    break;
                default:
                    $unit = '-'; //TODO
                    break;
            }
            $cmd->setUnite($unit);
            $cmd->setConfiguration('historizeMode', 'avg');
            $cmd->setConfiguration('historizeRound', 2);
            $cmd->setIsVisible(1);

          }

          $cmd->save();

          // va chopper la valeur de la commande puis la suivre a chaque changement
		  $value = $cmd->execCmd();
		  if (!is_numeric($value) || is_nan((float)$value) || $value == '') {
          //if (is_nan($cmd->execCmd()) || $cmd->execCmd() == '') {
            $cmd->setCollectDate('');
            $cmd->event($cmd->execute());
          }

        } //*/ // fin foreach restant. A partir de maintenant on a des capteurs qui refletent notre config lue en JS
      }


      //########## 4 - Mise en place des listeners de capteurs pour réagir aux events #########//

      if ($this->getIsEnable() == 1) { // si notre eq est actif, on va lui definir nos listeners de capteurs

        // un peu de menage dans nos events avant de remettre tout ca en ligne avec la conf actuelle
        $this->cleanAllListener();

        // on boucle dans toutes les cmd existantes
        foreach ($this->getCmd() as $cmd) {

          // on assigne la fonction selon le type de capteur
          if ($cmd->getLogicalId() == 'sensor_confort'){
            continue; // on veut pas de listener pour les capteurs confort ! Donc on coupe la boucle et on passe au prochain cmd
        //    $listenerFunction = 'sensorConfort';
          } else if ($cmd->getLogicalId() == 'sensor_security'){
            $listenerFunction = 'sensorSecurity';
          } else if ($cmd->getLogicalId() == 'sensor_cancel_security'){
            $listenerFunction = 'sensorSecurityCancel';
          }

          // on set le listener associée
          $listener = listener::byClassAndFunction('seniorcarecomfortsecurity', $listenerFunction, array('seniorcarecomfortsecurity_id' => intval($this->getId())));
          if (!is_object($listener)) { // s'il existe pas, on le cree, sinon on le reprend
            $listener = new listener();
            $listener->setClass('seniorcarecomfortsecurity');
            $listener->setFunction($listenerFunction); // la fct qui sera appellée a chaque evenement sur une des sources écoutée
            $listener->setOption(array('seniorcarecomfortsecurity_id' => intval($this->getId())));
          }
          $listener->addEvent($cmd->getValue()); // on ajoute les event à écouter de chacun des capteurs definis. On cherchera le trigger a l'appel de la fonction si besoin

          log::add('seniorcarecomfortsecurity', 'debug', 'sensor listener set - cmd :' . $cmd->getHumanName() . ' - event : ' . $cmd->getValue());

          $listener->save();

        } // fin foreach cmd du plugin
      } // fin if eq actif
      else { // notre eq n'est pas actif ou il a ete desactivé, on supprime les listeners s'ils existaient

        $this->cleanAllListener();

      }

    } // fin fct postSave

    // preUpdate ⇒ Méthode appellée avant la mise à jour de votre objet
    // ici on vérifie la présence de nos champs de config obligatoire
    public function preUpdate() {

      $sensorsType = array( // liste des types avec des champs a vérifier
        'security',
        'cancel_alert_bt',
        'cancel_security'
      );

      foreach ($sensorsType as $type) {
        if (is_array($this->getConfiguration($type))) {
          foreach ($this->getConfiguration($type) as $sensor) { // pour tous les capteurs de tous les types, on veut un nom et une cmd
            if ($sensor['name'] == '') {
              throw new Exception(__('Le champs Nom pour les capteurs ('.$type.') ne peut être vide',__FILE__));
            }

            if ($sensor['cmd'] == '') { // TODO on pourrait aussi ici vérifier que notre commande existe pour pas avoir de problemes apres...
              throw new Exception(__('Le champs Capteur ('.$type.') ne peut être vide',__FILE__));
            }

            if($type == 'confort'){ // uniquement pour les capteurs conforts, vérif sur les champs seuils

              if ($sensor['seuilHaut'] !='' && !is_numeric($sensor['seuilHaut']) || $sensor['seuilBas'] !='' && !is_numeric($sensor['seuilBas'])) {
                throw new Exception(__('Capteur confort - ' . $sensor['name'] . ', les valeurs des seuils doivent être numériques', __FILE__));
              }

              if ($sensor['seuilBas'] >= $sensor['seuilHaut']) {
                throw new Exception(__('Capteur confort - ' . $sensor['name'] . ', les seuils doivent être définis et le seuil bas ne peut pas être supérieur ou égal au seuil haut', __FILE__)); // consequence : on peut pas ne definir qu'un seul seuil
              }

            }

          }
        }
      }
    }

    public function postUpdate() {

    }

    public function preRemove() {

      // quand on supprime notre eqLogic, on vire nos listeners associés
      $this->cleanAllListener();

    }

    public function postRemove() {

    }

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class seniorcarecomfortsecurityCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    public function execute($_options = array()) {

      log::add('seniorcarecomfortsecurity', 'debug', 'Fct execute pour : ' . $this->getLogicalId() . $this->getHumanName() . '- valeur renvoyée : ' . jeedom::evaluateExpression($this->getValue()));

      return jeedom::evaluateExpression($this->getValue());

    }

    /*     * **********************Getteur Setteur*************************** */
}
