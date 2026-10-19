
# Architecture

## Overview

The project is implemented as a Moodle block plugin named
`block_resourcerecommender`.

The repository root represents the plugin directory.

## Components

1. `block_resourcerecommender.php`
   - Defines the Moodle block.
   - Coordinates recommendation generation and display.

2. `classes/local/recommendation_engine.php`
   - Retrieves eligible course resources using Moodle APIs.
   - Calculates a relevance score for each candidate.
   - Sorts the candidates and returns up to five results.

3. `templates/recommendations.mustache`
   - Presents resource titles, links, and explanations.

4. `styles.css`
   - Styles the recommendation block.

5. `lang/en/block_resourcerecommender.php`
   - Stores interface text.

6. `version.php`
   - Defines the plugin component, version, and Moodle requirement.

7. `db/access.php`
   - Defines plugin access capabilities.

8. `tests/recommendation_engine_test.php`
   - Tests recommendation behavior.

## Recommendation rules

- Exclude the resource currently being viewed.
- Exclude resources the current user cannot access.
- Prefer candidates with matching keywords.
- Prefer candidates from the same course section.
- Prefer candidates of a related resource type.
- Return no more than five recommendations.

The weights and tie-breaking behavior will be refined during testing.

## Data flow

1. Moodle provides the current course and page context.
2. The block calls the recommendation engine.
3. The engine obtains eligible course modules and calculates scores.
4. The engine returns an ordered list of candidates.
5. The block passes the result to the Mustache template.
6. Moodle renders the recommendation block.

## Scope of version 1

The initial version does not use machine learning, external APIs,
or a custom database table. It does not collect individual browsing
histories.
