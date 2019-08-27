<?php

declare(strict_types=1);

/*
 * @copyright   2018 Mautic Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://www.mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\IntegrationsBundle\Exception\IntegrationNotFoundException;
use MauticPlugin\IntegrationsBundle\Form\Type\IntegrationSyncSettingsObjectFieldMappingType;
use MauticPlugin\IntegrationsBundle\Helper\ConfigIntegrationsHelper;
use MauticPlugin\IntegrationsBundle\Helper\FieldFilterHelper;
use MauticPlugin\IntegrationsBundle\Helper\FieldMergerHelper;
use MauticPlugin\IntegrationsBundle\Integration\Interfaces\ConfigFormSyncInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class FieldPaginationController extends CommonController
{
    /**
     * @param string  $integration
     * @param string  $object
     * @param int     $page
     * @param Request $request
     *
     * @return mixed
     */
    public function paginateAction(string $integration, string $object, int $page, Request $request)
    {
        // Check ACL
        if (!$this->get('mautic.security')->isGranted('plugin:plugins:manage')) {
            return $this->accessDenied();
        }

        // Find the integration
        /** @var ConfigIntegrationsHelper $integrationsHelper */
        $integrationsHelper = $this->get('mautic.integrations.helper.config_integrations');
        try {
            /** @var ConfigFormSyncInterface $integrationObject */
            $integrationObject        = $integrationsHelper->getIntegration($integration);
            $integrationConfiguration = $integrationObject->getIntegrationConfiguration();
        } catch (IntegrationNotFoundException $exception) {
            return $this->notFound();
        }

        $keyword         = $request->get('keyword', '');
        $featureSettings = $integrationConfiguration->getFeatureSettings();
        $currentFields   = $this->getFields($integrationObject, $featureSettings, $object);

        $fieldFilterHelper = new FieldFilterHelper($integrationObject);
        $fieldFilterHelper->filterFieldsByKeyword($object, $keyword, $page);

        // Create the form
        $form = $this->get('form.factory')->create(
            IntegrationSyncSettingsObjectFieldMappingType::class,
            $currentFields,
            [
                'integrationFields' => $fieldFilterHelper->getFilteredFields(),
                'page'              => $page,
                'keyword'           => $keyword,
                'totalFieldCount'   => $fieldFilterHelper->getTotalFieldCount(),
                'object'            => $object,
                'integrationObject' => $integrationObject,
                'csrf_protection'   => false,
            ]
        );

        $html = $this->render(
            'IntegrationsBundle:Config:field_mapping.html.php',
            [
                'form'        => $form->createView(),
                'integration' => $integration,
                'object'      => $object,
                'page'        => $page,
            ]
        )->getContent();

        $prefix   = "integration_config[featureSettings][sync][fieldMappings][$object]";
        $idPrefix = str_replace(['][', '[', ']'], '_', $prefix);
        if (substr($idPrefix, -1) == '_') {
            $idPrefix = substr($idPrefix, 0, -1);
        }

        $formType = 'integration_sync_settings_object_field_mapping';
        $html     = preg_replace('/'.$formType.'\[(.*?)\]/', $prefix.'[$1]', $html);
        $html     = str_replace($formType, $idPrefix, $html);

        return new JsonResponse(
            [
                'success' => 1,
                'html'    => $html,
            ]
        );
    }

    private function getFields(ConfigFormSyncInterface $integrationObject, array $featureSettings, string $object): array
    {
        $fields = (isset($featureSettings['sync']['fieldMappings'])) ? $featureSettings['sync']['fieldMappings'] : [];

        if (!isset($fields[$object])) {
            $fields[$object] = [];
        }

        // Pull those changed from session
        $session       = $this->get('session');
        $sessionFields = $session->get(sprintf("%s-fields", $integrationObject->getName()), []);

        if (!isset($sessionFields[$object])) {
            return $fields[$object];
        }

        $fieldMerger = new FieldMergerHelper($integrationObject, $fields);
        $fieldMerger->mergeSyncFieldMapping($object, $sessionFields[$object]);

        return $fieldMerger->getFieldMappings()[$object];
    }
}
