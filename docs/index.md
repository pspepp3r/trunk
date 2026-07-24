---
layout: home

hero:
  name: Trunk
  text: Async PHP, API-first.
  tagline: A ReactPHP-based framework for building REST, GraphQL, and event-driven APIs without blocking the event loop.
  image:
    src: /logo.svg
    alt: Trunk
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: Routing
      link: /guide/routing

features:
  - title: Non-blocking core
    details: Router, middleware pipeline, ORM, sessions, and the console all run on a single ReactPHP event loop - no PHP-FPM, no per-request process spin-up.
  - title: DI container with autowiring
    details: A PSR-11 container that autowires constructor dependencies by reflection, with singleton and factory bindings when you need more control.
  - title: REST, GraphQL, and validated requests
    details: Route model binding, FormRequest validation, and a GraphQL endpoint backed by the same async ORM queries as your REST controllers.
  - title: Migrations and a Data Mapper ORM
    details: A Schema/Blueprint DSL for MySQL and PostgreSQL, plus an EntityManager/Repository pair that keeps entities as plain PHP objects.
  - title: JWT auth and per-route middleware
    details: Issue and verify bearer tokens, and apply middleware to individual routes instead of only globally.
  - title: Event-driven by convention
    details: Typed events, config-driven listener registration, and a dispatchAsync() for side effects that shouldn't block the response.
---
