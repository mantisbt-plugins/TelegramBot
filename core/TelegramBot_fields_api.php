<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

function field_get( $p_current_action ) {
    $t_inline_keyboard = keyboard_projects_get( $p_current_action['get_project'], $p_current_action['page'], $p_current_action['from_page'] );
}
