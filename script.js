$(document).ready(function () {
    $("#submitmsg").click(function () {
        var clientmsg = $("#usermsg").val();
        $.post("post.php", { text: clientmsg });
        $("#usermsg").val("");
        return false;
    });

    function loadFromServer() {
        $.ajax({
            url: "log.html",
            cache: false,
            success: function (t) {
                $("#chatbox").html(t);
            }
        });
    }

    setInterval(loadFromServer, 100);

    $("#exit").click(function () {
        var exit = confirm("Are you sure you want to leave the room?");
        if (exit == true) {
            window.location = "index.php?logout=true";
        }

    });
});