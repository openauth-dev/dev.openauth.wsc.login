{if OPENAUTH_CLIENT_ID !== "" && OPENAUTH_CLIENT_SECRET !== ""}
    {if $__wcf->user->openAuthID && $__wcf->user->openAuthAvatar}
        <dl class="avatarType">
            <dt>
                <img src="{$__wcf->user->openAuthAvatar}" alt="" class="userAvatarImage" style="width: 96px; height: 96px" />
            </dt>
            <dd>
                <label><input type="radio" name="avatarType" value="OpenAuth" {if $avatarType == 'OpenAuth'}checked="checked" {/if}/> {lang}wcf.user.avatar.type.openauth{/lang}</label>
                <small>{lang}wcf.user.avatar.type.openauth.description{/lang}</small>
                {if $errorField == 'OpenAuth'}
                    <small class="innerError">
                        {$errorType}
                    </small>
                {/if}
            </dd>
        </dl>
    {/if}
{/if}
