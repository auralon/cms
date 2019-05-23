<?php
namespace craft\gql;

use craft\errors\GqlException;
use GraphQL\Type\Definition\Type;

/**
 * Class TypeLoader
 */
class TypeLoader
{
    /**
     * @var callable[]
     */
    private static $_typeLoaders = [];

    /**
     * @param string $type
     * @return Type
     * @throws GqlException
     */
    public static function loadType(string $type): Type
    {
        if (!empty(self::$_typeLoaders[$type])) {
            $loader = self::$_typeLoaders[$type];

            return $loader();
        }

        throw new GqlException('Tried to load an unregistered type „' . $type . '” ');
    }

    public static function registerType(string $type, callable $loader) {
        self::$_typeLoaders[$type] = $loader;
    }
}