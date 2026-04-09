$(document).ready(function(){
    $("#submitmsg").click(function(){
        var clientmsg = $("#usermsg").val();
        $.post("post.php", { text: clientmsg });
        $("#usermsg").val("");
        return false;
    });

    function loadFromServer(){
        $.ajax({
            url: "log.txt",
            cache: false, 
            success: function(t){
                $("#chatbox").html(t);
            }
        });
    }

    setInterval(loadFromServer, 100);
});