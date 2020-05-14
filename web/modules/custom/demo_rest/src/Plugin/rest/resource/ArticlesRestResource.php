<?php

namespace Drupal\demo_rest\Plugin\rest\resource;

use Drupal\Core\Database\Connection;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "articles_rest_resource",
 *   label = @Translation("Articles rest resource"),
 *   uri_paths = {
 *     "canonical" = "/rest/articles"
 *   }
 * )
 */
class ArticlesRestResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The database connection
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The file system stream wrapper
   *
   * @var string
   */
  protected $fileDir;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('demo_rest');
    $instance->currentUser = $container->get('current_user');
    $instance->database = $container->get('database');
    $instance->fileDir = $container->get('stream_wrapper.public')->getDirectoryPath();
    return $instance;
  }

    /**
     * Responds to GET requests.
     *
     * @param string $payload
     *
     * @return \Drupal\rest\ResourceResponse
     *   The HTTP response object.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     *   Throws exception expected.
     */
    public function get($payload) {

        // You must to implement the logic of your REST Resource here.
        // Use current user after pass authentication to validate access.
        if (!$this->currentUser->hasPermission('access content')) {
            throw new AccessDeniedHttpException();
        }

        try {
          $query = $this->database->select('node_field_data', 'nfd');
          $query->condition('nfd.type', 'article');
    
          $query->join('node__field_image', 'n_fi', 'n_fi.entity_id = nfd.nid');
          $query->join('file_managed', 'f', 'f.fid = n_fi.field_image_target_id');
    
          $query->addField('nfd', 'title');
          $query->addExpression("REPLACE(f.uri, 'public:/', :base_path)", 'image', [':base_path' => $this->fileDir]);
    
          // $string = $query->execute()->getQueryString();
    
          $articles = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    
          $response = new ResourceResponse(['articles' => $articles]);
          $response->setMaxAge(strtotime('1 day', 0));
    
          return $response;
        }
        catch (\Exception $e) {
          throw new BadRequestHttpException($this->t('Could not find articles'), $e);
        }
    }

}
