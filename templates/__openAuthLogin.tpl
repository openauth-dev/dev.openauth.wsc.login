{if OPENAUTH_CLIENT_ID !== "" && OPENAUTH_CLIENT_SECRET !== ""}
    <li id="openAuth" class="thirdPartyLogin">
        <a href="{link controller='OpenAuth'}{/link}" class="thirdPartyLoginButton openAuthLoginButton button"><span class="icon icon24 fa-openauth"></span> <span>{lang}wcf.user.3rdparty.openauth.login{/lang}</span></a>
    </li>
{/if}
