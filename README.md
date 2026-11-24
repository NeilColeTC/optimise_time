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
- Teacher workload profiles with remission breakdowns and individual contact-hour caps
- Teaching demand by group and subject, including weekly hours, cohort size, and room type
- Room inventory with capacity and active status
- Configurable teaching days and time blocks, including breaks and period duration
- Teacher subject eligibility and period-level availability/preferences
- Configurable hard rule for evening duty and a contiguous half-day in lieu
- Recurring weekly evening-duty assignments linked to the in-lieu constraint
- SQL schema for staff workload, remissions, availability, courses, rooms, timetable slots, rules, and generated sessions
- Environment-based database configuration

The timetable generation engine and result review are the next implementation slices. Scheduling rules should be validated before an output is marked as valid.

## Setup

1. Create a MySQL database and import `database/schema.sql`.
2. Copy `.env.example` to `.env` and set the database connection values.
3. Run `composer install`.
4. Serve the `public` directory as the web root, for example with `php -S localhost:8000 -t public`.

The dashboard can render without a configured database; it displays setup guidance until the connection is available.

The teacher screen supports adding, editing, and deactivating profiles. Remission and reduced-timetable hours are subtracted from allocated teaching hours to show an expected teaching target; subject eligibility and the personal in-class maximum remain separate scheduling inputs.

## Workload model

Each teacher has separate weekly values for allocated teaching minutes, remission minutes, reduced-timetable minutes, and maximum in-class minutes. This keeps expected allocation distinct from the individual contact-hour cap.

Evening work, eligible in-lieu days, and the length of the uninterrupted half-day block are configurable. The generator must reserve a valid block for each teacher assigned an evening duty.

