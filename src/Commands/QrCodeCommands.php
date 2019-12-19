<?php

namespace Drupal\qr_code_attendance\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drush\Commands\DrushCommands;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class QrCodeCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * The entity storage for users.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The current data time.
   *
   * @var \Drupal\Core\Datetime\DrupalDateTime
   */
  private $dateNow;


  /**
   * Constructs a Service object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity type manager.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->userStorage = $entity_type_manager->getStorage('user');
    $this->dateNow = new DrupalDateTime('now');
  }

  public function generateQrCodeImage(array $data) {
    $text = Json::encode($data);
    $qrCode = new QrCode($text);
    $qrCode->setSize(300);
    // Set advanced options
    $qrCode->setWriterByName('png');
    $qrCode->setMargin(10);
    $qrCode->setEncoding('UTF-8');
    $qrCode->setForegroundColor(['r' => 0, 'g' => 177, 'b' => 64, 'a' => 1]);
    $qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0]);
    $qrCode->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH());
    $qrCode->setLogoSize(50, 750);
    $qrCode->setRoundBlockSize(true);
    $qrCode->setValidateResult(false);
    $qrCode->setWriterOptions(['exclude_xml_declaration' => true]);

    $file_name = implode('_', [$data['name'], $data['uid']]);
    $file_name = preg_replace('/[^A-Za-z0-9_]/', '', $file_name);
    $file_name .= '.png';

    $temp_uri = 'temporary://' . $file_name;
    $wrapperObj = $this->streamWrapperManager->getViaUri($temp_uri);
    $file_path = $wrapperObj->realpath();

    // Write QR to temp location.
    $qrCode->writeFile($file_path);

    // Move file to public directory.
    $fileContent = file_get_contents($temp_uri);
    $date_prefix = $this->dateNow->format('Y-m');
    $destination_directory = 'public://qr_codes/users/' . $date_prefix. '/';

    $destination =  $destination_directory . $file_name;
    $file = file_save_data($fileContent, $destination, FileSystemInterface::CREATE_DIRECTORY);
    return  [
      'file_id' => $file->id(),
      'file_path' => $file->createFileUrl(),
    ];
  }

  /**
   * Command description here.
   *
   * @command qr_code:gen
   * @aliases qcgen
   */
  public function generateQrCodeForUser($field_name = 'field_qr_image') {
    // Fetch users.
    $query = $this->userStorage->getQuery();
    $query->condition('uid', [0, 1], 'NOT IN');
    $query->condition('field_qr_image', NULL, 'IS NULL');
    $query->sort('uid', 'asc');
    $uids = $query->execute();
    $users = $this->userStorage->loadMultiple($uids);

    /** @var \Drupal\user\UserInterface $user */
    foreach ($users as $user) {
      $qr_details['uid'] = $user->id();
      $qr_details['name'] = $user->getAccountName();
      $qr_details['email'] = $user->getEmail() ?? '';
      $qr_details['mobile'] = $user->get('field_mobile_number')->first()->value;
      $qr_details = array_filter($qr_details,'strlen');
      $file_details = $this->generateQrCodeImage($qr_details);
      $user->set('field_qr_image', $file_details['file_id']);
      $user->save();
    }
  }

  /**
   * Command description here.
   *
   * @param $arg1
   *   Argument description.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   * @option option-name
   *   Description
   * @usage qr_code-commandName foo
   *   Usage description
   *
   * @command qr_code:commandName
   * @aliases foo
   */
  public function commandName($arg1, $options = ['option-name' => 'default']) {
    $this->logger()->success(dt('Achievement unlocked.'));
  }

  /**
   * An example of the table output format.
   *
   * @param array $options An associative array of options whose values come
   *   from cli, aliases, config, etc.
   *
   * @field-labels
   *   group: Group
   *   token: Token
   *   name: Name
   * @default-fields group,token,name
   *
   * @command qr_code:token
   * @aliases token
   *
   * @filter-default-field name
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function token($options = ['format' => 'table']) {
    $all = \Drupal::token()->getInfo();
    foreach ($all['tokens'] as $group => $tokens) {
      foreach ($tokens as $key => $token) {
        $rows[] = [
          'group' => $group,
          'token' => $key,
          'name' => $token['name'],
        ];
      }
    }
    return new RowsOfFields($rows);
  }
}
