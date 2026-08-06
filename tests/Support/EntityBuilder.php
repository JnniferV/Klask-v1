<?php

namespace App\Tests\Support;

use App\Entity\Activity;
use App\Entity\ActivityCategory;
use App\Entity\Group;
use App\Entity\Sphere;
use App\Entity\User;


final class EntityBuilder
{
    /**
     * @template T of object
     * @param  T $entity
     * @return T
     */
    public static function withId(object $entity, int $id): object
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);

        return $entity;
    }

    public static function category(string $type = ActivityCategory::TYPE_STAND, int $points = 50): ActivityCategory
    {
        return (new ActivityCategory())->setType($type)->setNbrPoints($points);
    }

    public static function sphere(int $id, string $name = 'Sphère'): Sphere
    {
        $sphere = (new Sphere())->setName($name)->setColor('#123456');

        return self::withId($sphere, $id);
    }

    public static function activity(int $id, ActivityCategory $category, ?Sphere $sphere = null, string $name = 'Stand'): Activity
    {
        $activity = (new Activity())
            ->setName($name . ' ' . $id)
            ->setCategory($category)
            ->setSphere($sphere);

        return self::withId($activity, $id);
    }

    public static function group(int $id = 1, int $score = 0): Group
    {
        $group = (new Group())->setCode('GRP0001')->setColor('#000000')->setScore($score);

        return self::withId($group, $id);
    }

    public static function student(int $id = 1, ?Group $group = null, int $score = 0): User
    {
        $user = (new User())->setPseudo('Renard cosmique')->setScore($score)->setGroup($group);

        return self::withId($user, $id);
    }
}
