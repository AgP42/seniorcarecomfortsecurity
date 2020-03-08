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

class seniorcare extends eqLogic {
    /*     * *************************Attributs****************************** */



    /*     * ***********************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
      public static function cron() {

      }
     */


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



    /*     * *********************Méthodes d'instance************************* */

    public function preInsert() {

    }

    public function postInsert() {

    }

    public function preSave() {

    }

    public function postSave() {

      //********** Pour les capteurs confort ***********//

      $jsSensorConfort = array(); // on va stocker les sensor confort du JS ici, s'ils contiennent une valeur dans le champs cmd et un nom

      if (is_array($this->getConfiguration('confort'))) {
        foreach ($this->getConfiguration('confort') as $confort) {
          if ($confort['name'] != '' && $confort['cmd'] != '') {

            $jsSensorConfort[$confort['name']] = $confort;
        //    log::add('seniorcare', 'error', 'Capteurs confort config : ' . $confort['cmd'] . ' - ' . $confort['sensor_confort_type'] . ' - ' . $confort['seuilBas'] . ' - ' . $confort['seuilHaut']);

          }
        }
      }

      foreach ($this->getCmd() as $cmdSensorConfort) { // on boucle dans toutes les cmd existantes, pour les modifier si besoin
        if ($cmdSensorConfort->getLogicalId() == 'SensorConfort') { // si c'est une cmd "SensorConfort"
          if (isset($jsSensorConfort[$cmdSensorConfort->getName()])) { // on regarde si le nom correspond a un nom dans le tableau qu'on vient de recuperer du JS, si oui, on actualise les infos qui pourraient avoir bougé

        //    log::add('seniorcare', 'debug', 'Deja existant : ' . $cmdSensorConfort->getName());

            $cmdSensorConfort->setValue($confort['cmd']);
            $cmdSensorConfort->setConfiguration('seuilBas', $confort['seuilBas']);
            $cmdSensorConfort->setConfiguration('seuilHaut', $confort['seuilHaut']);
            $cmdSensorConfort->setGeneric_type($confort['sensor_confort_type']);
            switch ($confort['sensor_confort_type']) {
                case 'temperature':
                    $unit = '°C';
                    break;
                case 'humidite':
                    $unit = '%';
                    break;
                case 'co2':
                    $unit = 'ppm'; //TODO
                    break;
                case 'pollution':
                    $unit = '?'; //TODO
                    break;
                default:
                    $unit = '?'; //TODO
                    break;
            }
            $cmdSensorConfort->setUnite($unit);

            $cmdSensorConfort->save();

            unset($jsSensorConfort[$cmdSensorConfort->getName()]); // on a traité notre ligne, on la vire pour pas repasser dessus dans le foreach suivant

          } else { // on a un SensorConfort qui était dans la DB mais dont le nom n'est plus dans notre JS : on la supprime ! Attention, si on a juste changé le nom, on va le supprimer et le recreer, donc perdre l'historique éventuel. //TODO : voir si ca pose probleme
            $cmdSensorConfort->remove();
          }
        }
      }

      foreach ($jsSensorConfort as $confort) { // pour tous ceux restant (ils sont dans le tableau JS, mais n'étaient pas deja en DB) : il faut les créer.

    //    log::add('seniorcare', 'debug', 'Capteurs confort config : ' . $confort['cmd'] . ' - ' . $confort['sensor_confort_type'] . ' - ' . $confort['seuilBas'] . ' - ' . $confort['seuilHaut']);

        $cmdSensorConfort = new seniorcareCmd();
        $cmdSensorConfort->setEqLogic_id($this->getId());
        $cmdSensorConfort->setLogicalId('SensorConfort');
        $cmdSensorConfort->setName($confort['name']);
        $cmdSensorConfort->setValue($confort['cmd']);
        $cmdSensorConfort->setConfiguration('seuilBas', $confort['seuilBas']);
        $cmdSensorConfort->setConfiguration('seuilHaut', $confort['seuilHaut']);
        $cmdSensorConfort->setGeneric_type($confort['sensor_confort_type']);
        $cmdSensorConfort->setType('info');
        $cmdSensorConfort->setSubType('numeric');
        switch ($confort['sensor_confort_type']) {
            case 'temperature':
                $unit = '°C';
                break;
            case 'humidite':
                $unit = '%';
                break;
            case 'co2':
                $unit = 'ppm'; //TODO
                break;
            case 'pollution':
                $unit = '?'; //TODO
                break;
            default:
                $unit = '?'; //TODO
                break;
        }
        $cmdSensorConfort->setUnite($unit);
        $cmdSensorConfort->setIsVisible(0);
        $cmdSensorConfort->setIsHistorized(1);
        $cmdSensorConfort->setConfiguration('historizeMode', 'avg');
        $cmdSensorConfort->setConfiguration('historizeRound', 2);
        $cmdSensorConfort->save();

      }


    }

    // preUpdate ⇒ Méthode appellée avant la mise à jour de votre objet
    // ici on vérifie la présence de nos champs de config obligatoire
    public function preUpdate() {

      /************ Pour les capteurs de confort, il faut un nom et une cmd ***********/
      if (is_array($this->getConfiguration('confort'))) {
        foreach ($this->getConfiguration('confort') as $confort) {
          if ($confort['name'] == '') {
            throw new Exception(__('Le champs Nom pour les capteurs de confort ne peut être vide',__FILE__));
          }

          if ($confort['cmd'] == '') {
            throw new Exception(__('Le champs Capteur pour les capteurs de confort ne peut être vide',__FILE__));
          }

          if (!is_numeric($confort['seuilHaut']) || !is_numeric($confort['seuilBas'])) {
            throw new Exception(__('Capteur confort - ' . $confort['name'] . ', les valeurs des seuils doivent être numérique', __FILE__));
          }

          if ($confort['seuilBas'] > $confort['seuilHaut']) {
            throw new Exception(__('Capteur confort - ' . $confort['name'] . ', le seuil bas ne peut pas être supérieur au seuil haut', __FILE__));
          }

        }
      }

    }

    public function postUpdate() {

    }

    public function preRemove() {

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

class seniorcareCmd extends cmd {
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

    }

    /*     * **********************Getteur Setteur*************************** */
}


