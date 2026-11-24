# Academic Timetable Optimiser

A standalone PHP MVC application for planning course delivery around staff workload, room capacity, term dates, and configurable timetable constraints.

## Stack

- PHP 8.3+ with Composer PSR-4 autoloading
- MySQL or MariaDB through PDO
- Tailwind CSS via CDN during the initial UI build

## Current foundation

- Teacher workload profiles with remission breakdowns, reduced timetables, individual in-class caps, and unit eligibility
- Curriculum hierarchy: courses contain units; units can be reused across courses
- Course totals for hours and credits; unit totals for hours and credits
- Course-unit offerings with cohort size, room type, date range, and delivery pattern: Semesterised (FAT) or Weekly (Long)
- Academic years, terms, and bank holiday records
- Configurable protected periods for English/Maths, meetings/training, and custom commitments, scoped to all courses or vocational courses
- Room inventory with capacity/type and active status
- Configurable teaching days, periods, and breaks
- Teacher availability and preferred periods
- Configurable evening-duty rule requiring an uninterrupted half-day in lieu on another day
- Recurring weekly evening-duty assignments
- SQL schema, CSRF protection, parameterized writes, and environment-based database configuration

## Setup

1. Create a MySQL database and import `database/schema.sql`.
2. Copy `.env.example` to `.env` and set the database connection values.
3. Run `composer install`.
4. Serve `public` with the front controller, for example `php -S localhost:8000 -t public public/index.php`.

Configure academic years and terms in **Academic calendar**. Term dates must sit within the selected academic year. Course-unit offering dates are validated against the selected year and, when selected, its term. Add bank holidays so the future timetable generator can exclude those dates.

Teachers are linked to units, allowing a teacher to deliver one unit on multiple courses. A vocational course can be marked as requiring English/Maths delivery. Protected blocks are saved as hard scheduling inputs; the timetable generation engine and result review are still to be built, and will need to enforce these dates, blocks, workload caps, room capacity, and teaching demand before marking a schedule valid.
