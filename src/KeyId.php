<?php declare(strict_types=1);

namespace Fieldstone\Couchbase;

use Webpatser\Uuid\Uuid;

/**
 * Class MultiTenantKeyId
 * @package Fieldstone\Couchbase
 *
 * This class handles formatting the key id which is stored in documents
 * in the built-in _id field.
 *
 */
class KeyId
{
    public const SegmentSeparator = '-';
    public const AllTenantIndicator = '*';
    public const TenantPosition = 0;
    public const TypePosition = 1;
    public const ObjectIdPosition = 2;

    /**
     * @param $type
     * @param string $tenantUuid
     * @return string|null
     * @throws \Exception
     */
    public static function getNewId($type, $tenantUuid = self::AllTenantIndicator)
    {
         return $tenantUuid
            . self::SegmentSeparator
            . $type
             .self::SegmentSeparator
             .Uuid::generate(4);
    }

    public static function getTenantId($multiTenantKeyId)
    {
        return explode(self::SegmentSeparator, $multiTenantKeyId)[self::TenantPosition];
    }

    public static function getDocumentType($multiTenantKeyId)
    {
        return explode(self::SegmentSeparator, $multiTenantKeyId)[self::TypePosition];
    }

    public static function getDocumentUuid($multiTenantKeyId)
    {
        return explode(self::SegmentSeparator, $multiTenantKeyId)[self::ObjectIdPosition];
    }

    public static function fKeyIsProperFormat($sKeyValue){
        $split = explode(self::SegmentSeparator, $sKeyValue);
        return count($split) === 3;
    }

}
