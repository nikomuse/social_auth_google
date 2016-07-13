<?php

namespace Drupal\social_auth_google\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\social_auth_google\GoogleAuthManager;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\SocialAuthUserManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Zend\Diactoros\Response\RedirectResponse;

/**
 * Manages requests to Google API
 */
class GoogleAuthController extends ControllerBase {

  /**
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  private $networkManager;

  /**
   * @var \Drupal\social_auth_google\GoogleAuthManager
   */
  private $googleManager;
  /**
   * @var \Drupal\social_auth\SocialAuthUserManager
   */
  private $userManager;

  /**
   * GoogleLoginController constructor.
   *
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   * @param \Drupal\social_auth_google\GoogleAuthManager $google_manager
   * @param \Drupal\social_auth\SocialAuthUserManager $user_manager
   */
  public function __construct(NetworkManager $network_manager, GoogleAuthManager $google_manager, SocialAuthUserManager $user_manager) {
    $this->networkManager = $network_manager;
    $this->googleManager = $google_manager;
    $this->userManager = $user_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.network.manager'),
      $container->get('google_auth.manager'),
      $container->get('social_auth.user_manager')
    );
  }

  /**
   * Redirect to Google Services Authentication page
   *
   * @return \Zend\Diactoros\Response\RedirectResponse
   */
  public function redirectToGoogle() {
    /* @var \Google_Client $client */
    $client = $this->networkManager->createInstance('social_auth_google')->getSdk();
    $client->setScopes(array('email', 'profile'));

    return new RedirectResponse($client->createAuthUrl());
  }

  /**
   * Callback function to login user
   */
  public function callback() {
    /* @var \Google_Client $client */
    $client = $this->networkManager->createInstance('social_auth_google')->getSdk();

    $this->googleManager->setClient($client)
      ->authenticate()
      ->saveAccessToken('social_auth_google_token')
      ->createService();

    // If user information could be retrieved
    if($user = $this->googleManager->getUserInfo()) {
      // If user email has already an account in the site
      if($drupal_user = $this->userManager->loadUserByProperty('mail', $user->getEmail())) {
        if($this->userManager->loginUser($drupal_user)) {
          return $this->redirect('user.page');
        }
      }

      // If the new user could be registered
      if($drupal_user = $this->userManager->createUser($user->getName(), $user->getEmail())) {
        // If the new user could be logged in
        if($this->userManager->loginUser($drupal_user)) {
          return $this->redirect('user.page');
        }
      }
    }

    drupal_set_message($this->t('You could not be authenticated, please contact the administrator'), 'error');
    return $this->redirect('user.login');
  }
}