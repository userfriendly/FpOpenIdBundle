<?php
namespace Fp\OpenIdBundle\Security\Http\Firewall;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Fp\OpenIdBundle\RelyingParty\IdentityProviderResponse;
use Fp\OpenIdBundle\Security\Core\Authentication\Token\OpenIdToken;
use Fp\OpenIdBundle\Security\Http\Event\IdentityProvidedEvent;
use Fp\OpenIdBundle\Security\Http\SecurityEvents;

class OpenIdAuthenticationListener extends AbstractOpenIdAuthenticationListener
{
    /**
     * {@inheritdoc}
     */
    protected function attemptAuthentication(Request $request)
    {
        $openIdRequest = $request->duplicate();
        if (false == empty($this->options['required_attributes'])) {
            $openIdRequest->attributes->set('required_attributes', $this->options['required_attributes']);
        }
        if (false == empty($this->options['optional_attributes'])) {
            $openIdRequest->attributes->set('optional_attributes', $this->options['optional_attributes']);
        }

        $result = $this->getRelyingParty()->manage($openIdRequest);

        if ($result instanceof RedirectResponse) {
              //TODO not work for stable sf2.1             
//            if ($targetUrl = $request->get($this->options['target_path_parameter'], null, true)) {
//                $request->getSession()->set('_security.' . $this->providerKey . '.target_path', $targetUrl);
//            }
            
            return $result;
        }

        if ($result instanceof IdentityProviderResponse) {
            $token = new OpenIdToken($this->providerKey, $result->getIdentity());
            $token->setAttributes($result->getAttributes());

            try {
                return $this->authenticationManager->authenticate($token);
            } catch (AuthenticationException $e) {
                $e->setExtraInformation($result);

                throw $e;
            }
        }

        throw new \RuntimeException(sprintf(
            'The relying party %s::manage() must either return a RedirectResponse or instance of IdentityProviderResponse.',
            get_class($this->getRelyingParty())
        ));
    }
}
