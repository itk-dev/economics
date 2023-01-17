# Planning

## Description

Planning supplies an overview of remaining hours of work for each sprint, assignee and project.

Sub-tables can be expanded under each assignee/project with +, to the level of viewing which issues are allocated for
a given sprint.

## Sprint goals

Each cell in the "Assignee" overview is coloured according to sprint goals.

The weekly goals are set with the environment variables:

```dotenv
APP_WEEK_GOAL_LOW=25.0
APP_WEEK_GOAL_HIGH=34.5
```

## Data structure

The data structure is supplied in the `src/Model/Planning` folder.

## Jira implementation

For Jira the extraction of sprint data is dependent on a board. This default board is set with the parameter in .env:

```dotenv
JIRA_API_SERVICE_DEFAULT_BOARD=30
```
