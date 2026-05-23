<?php
/**
 * Controller.php
 * ------------------------------------------------------------
 * Base class every Controller extends.
 *
 * Provides:
 *  - $this->request                 The current Request object.
 *  - $this->view($name, $data)      Render a view.
 *  - $this->redirect($to)           HTTP redirect.
 *  - $this->json($data, $status)    JSON response.
 *  - $this->validate($rules)        Tiny rule-based input validator.
 *  - $this->old()                   Pull an "old input" value back into the form
 *                                   after a validation failure.
 * ------------------------------------------------------------
 */

namespace App\Core;

abstract class Controller
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    protected function view(string $name, array $data = []): void
    {
        View::render($name, $data);
    }

    protected function redirect(string $to, int $status = 302): never
    {
        Response::redirect($to, $status);
    }

    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    /**
     * Validate request input. Returns a sanitized array of inputs.
     * On failure: flashes errors + old input, then redirects back.
     *
     * Supported rules (string, pipe-separated):
     *   required
     *   min:N         (string length)
     *   max:N         (string length)
     *   confirmed     (matches a "<field>_confirmation" field)
     *   alpha_num     (letters/digits/underscores only)
     *
     * @param array<string,string> $rules  e.g. ['username' => 'required|min:3|max:50']
     * @param string|null          $redirectTo  Where to send the user on failure (defaults to current URL).
     * @return array<string,mixed> validated input values
     */
    protected function validate(array $rules, ?string $redirectTo = null): array
    {
        $values = [];
        $errors = [];

        foreach ($rules as $field => $ruleStr) {
            $value          = $this->request->input($field);
            $values[$field] = $value;
            $checks         = explode('|', $ruleStr);

            foreach ($checks as $check) {
                [$rule, $arg] = array_pad(explode(':', $check, 2), 2, null);

                switch ($rule) {
                    case 'required':
                        if ($value === null || $value === '') {
                            $errors[$field][] = ucfirst($field) . ' is required.';
                        }
                        break;

                    case 'min':
                        if (is_string($value) && mb_strlen($value) < (int) $arg) {
                            $errors[$field][] = ucfirst($field) . " must be at least {$arg} characters.";
                        }
                        break;

                    case 'max':
                        if (is_string($value) && mb_strlen($value) > (int) $arg) {
                            $errors[$field][] = ucfirst($field) . " must be at most {$arg} characters.";
                        }
                        break;

                    case 'confirmed':
                        $confirmField = $field . '_confirmation';
                        if ($value !== $this->request->input($confirmField)) {
                            $errors[$field][] = ucfirst($field) . ' confirmation does not match.';
                        }
                        break;

                    case 'alpha_num':
                        if (is_string($value) && $value !== '' && !preg_match('/^[A-Za-z0-9_]+$/', $value)) {
                            $errors[$field][] = ucfirst($field) . ' may only contain letters, digits and underscores.';
                        }
                        break;
                }
            }
        }

        if (!empty($errors)) {
            // Save errors + "old input" so the view can re-render the form nicely.
            Session::flash('errors', $errors);
            Session::flash('old',    $values);
            $back = $redirectTo ?? ($_SERVER['HTTP_REFERER'] ?? $this->request->uri());
            Response::redirect($back);
        }

        return $values;
    }

    /**
     * Convenience: pull a previously-submitted value (after validation failure).
     */
    protected function old(string $field, mixed $default = ''): mixed
    {
        $old = Session::getFlash('old', []);
        return $old[$field] ?? $default;
    }
}
