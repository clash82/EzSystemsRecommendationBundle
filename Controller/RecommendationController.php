<?php
/**
 * File containing the RecommendationController class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace EzSystems\RecommendationBundle\Controller;

use eZ\Bundle\EzPublishCoreBundle\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class RecommendationController
{
    protected $recommender, $repository, $router, $criteriaHelper;

    public function __construct( $recommender, $repository, $router, $criteriaHelper )
    {
        $this->recommender = $recommender;
        $this->repository = $repository;
        $this->router = $router;
        $this->criteriaHelper = $criteriaHelper;
    }

    /**
     * Transform location object into array
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Location $location
     * @param string $language
     * @return array
     */
    private function mapLocationToArray( $location, $language )
    {
        $contentData = $this->repository->getContentService()->loadContentByContentInfo( $location->valueObject->contentInfo );

        return array(
            'name' => $location->valueObject->contentInfo->name,
            'url' => $this->router->generate( $location->valueObject, array(), UrlGeneratorInterface::ABSOLUTE_PATH ),
            'image' => $contentData->getFieldValue( 'image', $language )->uri,
            'intro' => $contentData->getFieldValue( 'intro', $language )->xml->textContent,
            'timestamp' => $contentData->getVersionInfo()->creationDate->getTimestamp(),
            'author' => (string) $contentData->getFieldValue( 'author', $language )
        );
    }

    public function recommendationsAction( Request $request )
    {
        if ( !$request->isXmlHttpRequest() )
        {
            throw new BadRequestHttpException();
        }

        $userId = $this->repository->getCurrentUser()->id;
        $locationId = $request->query->get( 'locationId' );
        $limit = $request->query->get( 'limit' );
        $scenarioId = $request->query->get( 'scenarioId' );

        $responseRecommendations = $this->recommender->getRecommendations(
            $userId, $scenarioId, $locationId, $limit
        );

        $recommendedContentIds = array_map(
            function( $item )
            {
                return $item[ 'itemId' ];
            },
            $responseRecommendations[ 'recommendationResponseList' ]
        );

        $content = array();

        if ( count( $recommendedContentIds ) > 0 )
        {
            $contentQuery = $this->criteriaHelper->generateContentTypeCriterion( $recommendedContentIds );
            $searchService = $this->repository->getSearchService();
            $searchResults = $searchService->findLocations( $contentQuery );

            $language = $this->repository->getContentLanguageService()->getDefaultLanguageCode();

            foreach ($searchResults->searchHits as $result)
            {
                $content[] = $this->mapLocationToArray( $result, $language );
            }
        }

        $status = count( $content ) > 0 ? 'success' : 'empty';

        return new JsonResponse( array(
            'status' => $status,
            'content' => $content
        ) );
    }
}
