# Agent Instructions

To run CLI commands in the app container, do it like this:

  docker exec -it $(docker compose ps -q web) {command}

Always add the appropriate tests for the given code changes to be included in the same commit.

After every code change, run the full suite of linters and tests and fix any issues that arise:

- make quality
- make phpunit
- make playwright-test

Note that the makefile is full of all kinds of dev tools that you might need, like the above commands, so check its capabilities before constructing custom commands.

Don't commit automatically. Draft the commit message and wait for my confirmation. Commit messages titles should be written in the imperative mood and any body should explain why, not just give details on the changes being made. Keep it succinct.
