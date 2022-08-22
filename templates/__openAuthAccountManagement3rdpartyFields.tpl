{if OPENAUTH_CLIENT_ID !== "" && OPENAUTH_CLIENT_SECRET !== ""}
    <dl>
        <dt>{lang}wcf.user.3rdparty.openauth{/lang}</dt>
        <dd>
            {if $__wcf->getSession()->getVar('__oauthUser')}
                <label><input type="checkbox" name="openAuthConnect" value="1"{if $openAuthConnect} checked{/if}> {lang}wcf.user.3rdparty.openauth.connect{/lang}</label>
            {else}
                <a href="{link controller='OpenAuth'}{/link}" class="thirdPartyLoginButton openAuthLoginButton button"><span class="icon icon24 fa-openauth"></span> <span>{lang}wcf.user.3rdparty.openauth.connect{/lang}</span></a>
            {/if}
        </dd>
    </dl>
{/if}
