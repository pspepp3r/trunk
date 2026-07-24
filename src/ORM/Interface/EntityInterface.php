<?php

namespace Trunk\ORM\Interface;

/**
 * Marker interface for Data Mapper entities. The framework uses this to recognize
 * which plain PHP objects it's allowed to manage - notably, the Router only treats
 * a route-parameter-name-matched, class-typed handler argument as an implicit route
 * model binding (auto-resolved via EntityManager, 404 on miss) when its type
 * implements this interface, and EntityManager::getRepository() requires it too.
 */
interface EntityInterface {}
