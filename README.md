
# Moodle Resource Recommender

A Moodle block plugin for recommending relevant learning resources to students.

## Project

This project is developed for the E-Learning course.

**Topic:** Development of a custom Moodle plugin — learning resource recommendation plugin.

## Objectives

- Display recommended learning resources inside a Moodle course.
- Rank relevant resources using a transparent, rule-based algorithm.
- Respect Moodle resource visibility and user access permissions.
- Provide a simple and understandable interface for students.

## Planned technology

- Moodle plugin API
- PHP
- Moodle Mustache templates
- CSS
- Moodle-supported testing tools

## Recommendation approach

The first version uses resource metadata, keyword matching,
course sections, and resource types to calculate relevance scores.

The plugin will display up to five recommendations and will not
require an external recommendation service.

## Repository structure

- `classes/local/` — recommendation logic
- `db/` — plugin permissions
- `lang/en/` — interface strings
- `templates/` — recommendation display
- `tests/` — automated tests

See [ARCHITECTURE.md](ARCHITECTURE.md) for the design
and [INSTALL.md](INSTALL.md) for installation instructions.

## Project status

Planning and initial repository setup.

## Contributors

Bojan Trajkovski INKI1183
Filip Zdraveski INKI1182
Nikola Gjorgovski INKI1184
