<?php

namespace ScoutingMemories\Forms\Actions;

use ScoutingMemories\Forms\Forms\Logic\Conditions;

/**
 * ActionRunner
 *
 * Runs a form's Formidable "form actions" (frm_form_actions posts: post_excerpt = type,
 * menu_order = form ID, post_content = JSON settings) for an event such as "create".
 *
 * Supported: email, on_submit (returned to the caller, which shows the message or redirects).
 * Not yet: wppost (Phase 4), register (Phase 5); these are reported as skipped.
 */
class ActionRunner {

    /**
     * @param array<string, mixed> $context form, fields, values, entry
     * @return array{on_submit: array<string, mixed>|null, ran: string[], skipped: string[]}
     */
    public static function run(string $event, array $context): array {
        $result = ['on_submit' => null, 'ran' => [], 'skipped' => []];

        foreach (self::actionsFor((int) $context['form']['id']) as $action) {
            $settings = $action['settings'];
            $events = (array) ($settings['event'] ?? ['create']);
            if (!in_array($event, $events, true)) {
                continue;
            }
            if (!Conditions::actionShouldRun((array) ($settings['conditions'] ?? []), $context['values'])) {
                continue;
            }

            switch ($action['type']) {
                case 'email':
                    if (EmailAction::send($settings, $context)) {
                        $result['ran'][] = 'email:' . $action['id'];
                    }
                    break;
                case 'on_submit':
                    if ($result['on_submit'] === null) {
                        $result['on_submit'] = $settings;
                        $result['ran'][] = 'on_submit:' . $action['id'];
                    }
                    break;
                default:
                    $result['skipped'][] = $action['type'] . ':' . $action['id'];
            }
        }

        return $result;
    }

    /**
     * Published actions of a form, in Formidable's order.
     *
     * @return array<int, array{id:int, type:string, settings:array<string, mixed>}>
     */
    public static function actionsFor(int $formId): array {
        $posts = get_posts([
            'post_type' => 'frm_form_actions',
            'post_status' => 'publish',
            'menu_order' => $formId,
            'numberposts' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => true,
        ]);

        $actions = [];
        foreach ($posts as $post) {
            if ((int) $post->menu_order !== $formId) {
                continue;
            }
            $settings = json_decode($post->post_content, true);
            $actions[] = [
                'id' => (int) $post->ID,
                'type' => (string) $post->post_excerpt,
                'settings' => is_array($settings) ? $settings : [],
            ];
        }
        return $actions;
    }
}
