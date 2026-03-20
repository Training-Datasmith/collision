# Architecture: collision

## Purpose

Collision provides beautiful error reporting for PHP CLI applications. It replaces the default PHPUnit test output with a human-readable, colour-coded display and hooks into Laravel's exception handler to render rich terminal output for unhandled exceptions.

## Directory Structure

```
src/
  Provider.php                          — Entry point: registers the Collision handler with Whoops
  Handler.php                           — Whoops handler: receives exceptions and delegates to Writer
  Writer.php                            — Formats and outputs the exception to the terminal via Termwind/Symfony Console
  Highlighter.php                       — Syntax-highlights PHP source code around the exception site
  Argument_Formatter.php                — Formats stack frame arguments for display
  Console_Color.php                     — Terminal colour helpers
  Coverage.php                          — Code coverage integration utilities

  Adapters/
    Laravel/
      Collision_Service_Provider.php    — Laravel service provider: replaces the default exception handler
      Exception_Handler.php             — Laravel ExceptionHandler implementation using Collision
      Inspector.php                     — Inspects exceptions to extract stack trace metadata
      Ignition_Solutions_Repository.php — Integrates Ignition solution suggestions
      Commands/Test_Command.php         — `artisan test` command wrapper for Pest/PHPUnit

    Phpunit/
      Autoload.php                      — PHPUnit extension auto-registration
      State.php                         — Tracks test run state (pending, failing, passing)
      Style.php                         — Formats test results with Termwind
      Test_Result.php                   — Value object for a single test outcome
      Subscribers/                      — PHPUnit event subscribers (11.x event system)
      Printers/                         — PHPUnit result printer implementations
      Support/Result_Reflection.php     — Reflection helpers for extracting PHPUnit internals

  Contracts/
    Solutions_Repository.php            — Interface for solution suggestion backends
    Renderable_On_Collision_Editor.php  — Exceptions implementing this get custom display
    Renderless_Editor.php               — Marker: suppress the code-view panel
    Renderless_Trace.php                — Marker: suppress the stack trace panel

  Exceptions/
    Test_Exception.php                  — Wraps a failing test for display
    Test_Outcome.php                    — Enum-like: passed / failed / incomplete / etc.
    Invalid_Style_Exception.php
    Should_Not_Happen.php               — Defensive assertion exception

  SolutionsRepositories/
    Null_Solutions_Repository.php       — No-op implementation (default when Ignition not installed)
```

## Key Design Decisions

- **Whoops integration** — `Provider` registers Collision as a Whoops handler so existing Whoops-based error stacks receive the enhanced output automatically.
- **Adapter pattern** — separate `Laravel` and `Phpunit` adapters decouple framework-specific wiring from core rendering logic.
- **PHPUnit event subscribers** — uses PHPUnit 11+'s event-driven subscriber API (`TestPassedSubscriber`, `TestFailedSubscriber`, etc.) instead of the deprecated printer interface.
- **Termwind rendering** — output is built using Termwind's HTML-to-console renderer, producing rich terminal output without manual ANSI escape management.

## Extension Points

- Implement `Solutions_Repository` to provide contextual fix suggestions alongside exceptions (Ignition does this).
- Implement `Renderable_On_Collision_Editor` on a custom exception to control how it displays its code panel.
- Implement `Renderless_Trace` on an exception to suppress the stack trace (useful for user-facing CLI errors).

## Dependency Flow

```
PHP error / exception
  └── Whoops::handleException()
        └── Handler (Collision handler)
              └── Writer::write(Inspector)
                    ├── Highlighter (source code panel)
                    ├── Argument_Formatter (stack frames)
                    └── Solutions_Repository (suggested fixes)
```
