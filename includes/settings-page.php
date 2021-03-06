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
    $this->options['publishing'] = empty($_POST['publishing']) ? 0 : 1;
    $this->options['branch'] = empty($_POST['branch']) ? '' : (string) $_POST['branch'];

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

                <tr>
                    <th><label for="publishing"><?php _e('Run when publishing', 'bitbucket-pipelines');?></label></th>
                    <td><input type="checkbox" id="publishing" name="publishing" value="1" <?php checked($this->options['publishing']);?> /></td>
                </tr>
                <?php $branches = $this->get_branches(); ?>
                <?php if ($branches && $this->options['key'] && $this->options['secret'] && $this->options['workspace'] && $this->options['repo_slug']) {?>
                <tr>
                    <th><label for="branch"><?php _e('Pipeline Branch', 'bitbucket-pipelines');?></label></th>
                    <td>
                        <select name="branch" id="branch">
                            <option value="" <?php selected($this->options['branch'], '')?>><?php _e('Select Branch', 'bitbucket-pipelines');?></option>
                            <?php foreach ($branches as $branch) {?>
                                <option value="<?=$branch->name?>" <?php selected($this->options['branch'], $branch->name)?>><?=$branch->name?></option>
                            <?php }?>
                        </select>
                    </td>
                </tr>
                <?php }?>

            </tbody>
        </table>


        <?php wp_nonce_field('bitbucket-pipelines-admin');?>

        <p class="submit">
            <input class="button-primary" type="submit" name="submit" value="<?php _e('Save Changes', 'bitbucket-pipelines');?>">
        </p>

    </form>
</div>