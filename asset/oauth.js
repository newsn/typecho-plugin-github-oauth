function getElementsByClassName(node, classname) {
    if (node.getElementsByClassName) {
        //使用现有方法
        return node.getElementsByClassName(classname);
    } else {
        var results = new Array();
        var elems = node.getElementsByTagName("*");
        for (var i = 0; i < elems.length; i++) {
            if (elems[i].className.indexOf(classname) != -1) {
                results[results.length] = elems[i];
            }
        }
        return results;
    }
}
(function () {
    var icons = document.getElementsByClassName("github_login_icon");
    for (var i = 0; i < icons.length; i++) {
        icons[i].onclick = function () {
            var iWidth = 360;                          //弹出窗口的宽度; 
            var iHeight = 620;                         //弹出窗口的高度; 
            var iTop = (window.screen.availHeight - 30 - iHeight) / 2;
            var iLeft = (window.screen.availWidth - 10 - iWidth) / 2;
            window.open('/oauth/github/', '_blank', 'height=' + iHeight + ',,innerHeight=' + iHeight + ',width=' + iWidth + ',innerWidth=' + iWidth + ',top=' + iTop + ',left=' + iLeft + ',status=no,toolbar=no,menubar=no,location=no,resizable=no,scrollbars=0,titlebar=no');
        };
    }
})();