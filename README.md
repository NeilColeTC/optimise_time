# Academic Timetable Optimiser

A standalone PHP MVC application for building a weekly academic timetable around teaching demand, staff workload, rooms, and configurable rules.

## Stack

- PHP 8.3+
- MySQL or MariaDB via PDO
- Tailwind CSS (CDN during the initial UI build)
- Composer PSR-4 autoloading

## Current foundation

- MVC front controller and simple router
- Dashboard shell with navigation for timetable setup areas
- SQL schema for staff workload, remissions, availability, courses, rooms, timetable slots, rules, and generated sessions
- Environment-based database configuration

The timetable optimiser and data-entry screens are the next implementation slices. Scheduling rules should be validated before an output is marked as valid.

## Setup

1. Create a MySQL database and import `database/schema.sql`.
2. Copy `.env.example` to `.env` and set the database connection values.
3. Run `composer install`.
4. Serve the `public` directory as the web root, for example with `php -S localhost:8000 -t public`.

The dashboard can render without a configured database; it displays setup guidance until the connection is available.

## Workload model

Each teacher has separate weekly values for allocated teaching minutes, remission minutes, reduced-timetable minutes, and maximum in-class minutes. This keeps expected allocation distinct from the individual contact-hour cap.

Evening work and the half-day-in-lieu block are represented as configurable rules. The exact day and period block can be selected when the rule-management screen is built.

