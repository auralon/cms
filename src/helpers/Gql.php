<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\helpers;

use Craft;
use craft\errors\GqlException;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeLoader;
use GraphQL\Type\Definition\UnionType;

/**
 * Class Gql
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3
 */
class Gql
{
    /**
     * Cached permission pairs for the current token, as it's impossible for tokens to change during a request.
     *
     * @var null
     */
    private static $cachedPairs = null;

    /**
     * Returns true if the current token is aware of the provided scope(s).
     *
     * @param string|string[] $scopes The scope(s) to check.
     * @return bool
     * @throws GqlException
     */
    public static function isTokenAwareOf($scopes): bool
    {
        if (!is_array($scopes)) {
            $scopes = [$scopes];
        }

        try {
            $permissions = (array) Craft::$app->getGql()->getCurrentToken()->permissions;
        } catch (GqlException $exception) {
            Craft::$app->getErrorHandler()->logException($exception);
            return false;
        }

        foreach ($scopes as $scope) {
            if (empty(preg_grep('/^' . preg_quote($scope, '/') . '\:/i', $permissions))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extracts all the allowed entities from the token permissions for the action.
     *
     * @param string $action The action for which the entities should be extracted. Defaults to "read"
     * @return array
     */
    public static function extractAllowedEntitiesFromToken($action = 'read'): array
    {
        if (self::$cachedPairs === null) {
            try {
                $permissions = (array) Craft::$app->getGql()->getCurrentToken()->permissions;
                $pairs = [];

                foreach ($permissions as $permission) {
                    // Check if this is for the requested action
                    if (StringHelper::endsWith($permission, ':' . $action)) {
                        $permission = StringHelper::removeRight($permission, ':' . $action);

                        $parts = explode('.', $permission);

                        if (count($parts) === 2) {
                            $pairs[$parts[0]][] = $parts[1];
                        }
                    }
                }

                self::$cachedPairs = $pairs;
            } catch (GqlException $exception) {
                Craft::$app->getErrorHandler()->logException($exception);
                return [];
            }
        }

        return self::$cachedPairs;
    }

    /**
     * Returns true if the current token can perform the action on the scope.
     *
     * @param string $scope The scope to check.
     * @param string $action The action. Defaults to "read"
     * @return bool
     * @throws GqlException
     */
    public static function canToken($scope, $action = 'read'): bool
    {
        try {
            $permissions = (array) Craft::$app->getGql()->getCurrentToken()->permissions;
            return !empty(preg_grep('/^' . preg_quote($scope, '/') . '\:' . preg_quote($action, '/') . '$/i', $permissions));
        } catch (GqlException $exception) {
            Craft::$app->getErrorHandler()->logException($exception);
            return false;
        }
    }

    /**
     * Return true if current token can query entries.
     *
     * @return bool
     */
    public static function canQueryEntries(): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromToken();
        return isset($allowedEntities['sections'], $allowedEntities['entrytypes']);
    }

    /**
     * Return true if current token can query entries.
     *
     * @return bool
     */
    public static function canQueryAssets(): bool
    {
        return isset(self::extractAllowedEntitiesFromToken()['volumes']);
    }

    /**
     * Return true if current token can query entries.
     *
     * @return bool
     */
    public static function canQueryGlobalSets(): bool
    {
        return isset(self::extractAllowedEntitiesFromToken()['globalsets']);
    }

    /**
     * Return true if current token can query entries.
     *
     * @return bool
     */
    public static function canQueryUsers(): bool
    {
        return isset(self::extractAllowedEntitiesFromToken()['usergroups']);
    }

    /**
     * Get (and create if needed) a union type by name, included types and a resolver funcion.
     *
     * @param string $typeName The union type name.
     * @param array $includedTypes The type the union should include
     * @param callable $resolveFunction The resolver function to use to resolve a specific type.
     * @return mixed
     */
    public static function getUnionType(string $typeName, array $includedTypes, callable $resolveFunction)
    {
        $unionType = GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new UnionType([
            'name' => $typeName,
            'types' => $includedTypes,
            'resolveType' => $resolveFunction,
        ]));

        TypeLoader::registerType($typeName, function () use ($unionType) { return $unionType ;});

        return $unionType;
    }
}