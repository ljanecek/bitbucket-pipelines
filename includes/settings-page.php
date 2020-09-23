<?php
/**
 * Setting page.
 *
 * @package Bitbucket_Pipelines
 */

if (!defined('ABSPATH')) {
    exit;
}

if (isset($_POST['submit'])) {

    check_admin_referer('bitbucket-pipelines-admin');

    $this->options['key'] = empty($_POST['key']) ? '' : (string) $_POST['key'];
    $this->options['secret'] = empty($_POST['secret']) ? '' : (string) $_POST['secret'];
    $this->options['workspace'] = empty($_POST['workspace']) ? '' : (string) $_POST['workspace'];
    $this->options['repo_slug'] = empty($_POST['repo_slug']) ? '' : (string) $_POST['repo_slug'];

    $this->update_options();

    echo '<div id="message" class="updated"><p>' . __('Options updated.', 'bitbucket-pipelines') . '</p></div>';
}

?>
<div class="wrap">
    <h1><?php _ex('Bitbucket Pipelines Configuration', 'settings page title', 'bitbucket-pipelines');?></h1>

    <form action="" method="post" id="bitbucket-pipelines">

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th><label for="key"><?php _e('Key', 'bitbucket-pipelines');?></label></th>
                    <td><input name="key" id="key" type="text" value="<?=$this->options['key']?>" class="regular-text code"></td>
                </tr>
                <tr>
                    <th><label for="secret"><?php _e('Secret', 'bitbucket-pipelines');?></label></th>
                    <td><input name="secret" id="secret" type="text" value="<?=$this->options['secret']?>" class="regular-text code"></td>
                </tr>
                <tr>
                    <th><label for="workspace"><?php _e('Workspace', 'bitbucket-pipelines');?></label></th>
                    <td><input name="workspace" id="workspace" type="text" value="<?=$this->options['workspace']?>" class="regular-text code"></td>
                </tr>
                <tr>
                    <th><label for="repo_slug"><?php _e('Repo. slug', 'bitbucket-pipelines');?></label></th>
                    <td><input name="repo_slug" id="repo_slug" type="text" value="<?=$this->options['repo_slug']?>" class="regular-text code"></td>
                </tr>
            </tbody>
        </table>

        <p>
        <?php foreach($this->get_branches() as $branch){ ?>
            Branch: <strong><?=$branch->name?></strong> <br>
        <?php } ?>
        </p>

        <?php wp_nonce_field('bitbucket-pipelines-admin');?>

        <p class="submit">
            <input class="button-primary" type="submit" name="submit" value="<?php _e('Save Changes', 'bitbucket-pipelines');?>">
        </p>

    </form>
</div>