// Run after npm run build. Requires Playwright via PLAYWRIGHT_MODULE or a local installation.
// No real accounts, provider calls, or mutations: only the browser's API requests are stubbed.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || "playwright");
const assert = require("node:assert/strict");
const http = require("node:http");
const fs = require("node:fs");
const path = require("node:path");
const dist = path.resolve(__dirname, "../dist");
const accounts = {
  a: {id:101,nom:"Account A",email:"a@example.test",role:"client"},
  b: {id:202,nom:"Account B",email:"b@example.test",role:"client"},
};
function deferred() {
  let resolve;
  const promise = new Promise(r => {resolve=r;});
  return {promise,resolve};
}
(async () => {
  const server = http.createServer((req,res)=>{
    const relative = decodeURIComponent(new URL(req.url, "http://localhost").pathname);
    let file = path.resolve(dist, "." + relative);
    if (!file.startsWith(dist + path.sep) || !fs.existsSync(file) || fs.statSync(file).isDirectory()) file = path.join(dist,"index.html");
    res.setHeader("Content-Type", {".html":"text/html",".js":"text/javascript",".css":"text/css",".svg":"image/svg+xml",".webp":"image/webp"}[path.extname(file)] || "application/octet-stream");
    res.end(fs.readFileSync(file));
  });
  await new Promise(resolve=>server.listen(0,"127.0.0.1",resolve));
  const browser = await chromium.launch({channel:process.env.CHROME_CHANNEL || "chrome",headless:true});
  const context = await browser.newContext({viewport:{width:1440,height:1000}});
  // Intentionally emulate a transport that ignores cancellation to exercise the late-response guard.
  await context.addInitScript(()=>{
    const original = window.fetch.bind(window);
    window.fetch = (url, options={}) => original(url, {...options,signal:undefined});
  });
  const page = await context.newPage();
  page.setDefaultTimeout(10000);
  const chats=[];
  const errors=[];
  const heldA=deferred(), heldB=deferred(), failedB=deferred();
  const readyA=deferred(), readyB=deferred(), readyFailure=deferred();
  page.on("pageerror", error=>errors.push(error.message));
  await page.route("**/api/**", async route=>{
    const req=route.request();
    const endpoint=new URL(req.url()).pathname.replace(/^.*\/api/,"");
    const auth=req.headers().authorization;
    const account=auth==="Bearer token-a" ? accounts.a : auth==="Bearer token-b" ? accounts.b : null;
    const send = data=>route.fulfill({status:200,contentType:"application/json",body:JSON.stringify(data)});
    if (endpoint==="/login") {
      const which=req.postDataJSON().email.startsWith("a@") ? "a" : "b";
      return send({user:accounts[which],token:"token-"+which});
    }
    if (endpoint==="/logout") return send({message:"Logged out"});
    if (endpoint==="/user") return send(account);
    if (endpoint==="/favorites" || endpoint==="/cart") return send({items:[],total:"0.00"});
    if (endpoint.startsWith("/products")) return send({data:[],total:0});
    if (endpoint==="/categories") return send([]);
    if (endpoint==="/support/chat") {
      const data=req.postDataJSON();
      chats.push({data,auth,id:req.headers()["x-chat-request-id"]});
      if (data.message==="A delayed") {readyA.resolve();await heldA.promise;return send({status:"success",reply:"A private delayed answer"});}
      if (data.message==="B delayed") {readyB.resolve();await heldB.promise;return send({status:"success",reply:"B private answer"});}
      if (data.message==="B failed delayed") {readyFailure.resolve();await failedB.promise;return route.abort("failed");}
      return send({status:"success",reply:(account?.nom || "Guest")+" catalog answer"});
    }
    throw new Error("Unexpected API endpoint "+endpoint);
  });
  const pass = message=>console.log("PASS "+message);
  const open = async()=>page.getByRole("button",{name:"Open Elite AI shopping assistant"}).click();
  const text = async()=>page.locator(".support-chat-history").innerText();
  const send = async message=>{
    await page.locator(".support-chat-input-field").fill(message);
    await page.getByRole("button",{name:"Send message",exact:true}).click();
  };
  const settled = async()=>page.waitForFunction(()=>document.querySelector(".support-chat-input-field") && !document.querySelector(".support-chat-input-field").disabled);
  const login = async which=>{
    await page.locator(".nav-user-btn").click();
    await page.locator('input[name=email]').fill(accounts[which].email);
    await page.locator('input[name=mot_de_passe]').fill("test-only-password");
    await page.locator(".auth-submit").click();
    await page.waitForFunction(token=>localStorage.getItem("token")===token,"token-"+which);
    await page.getByRole("button",{name:"Open Elite AI shopping assistant"}).waitFor();
  };
  const logout = async()=>{
    await page.locator(".nav-user-btn").click();
    await page.getByRole("button",{name:"Sign Out",exact:true}).click();
    await page.waitForFunction(()=>!localStorage.getItem("token"));
    await page.getByRole("button",{name:"Open Elite AI shopping assistant"}).waitFor();
  };
  try {
    await page.goto("http://127.0.0.1:"+server.address().port);
    await open();await send("Guest question");await settled();
    assert.match(await text(),/Guest catalog answer/);
    assert.equal(chats[0].auth,undefined);
    assert.deepEqual(chats[0].data.history,[]);
    await send("Guest follow-up");await settled();
    assert.equal(chats[1].data.history.length,2);
    await page.locator(".support-chat-input-field").fill("Guest unsent private draft");
    pass("Guest chat has no bearer and keeps only its own in-memory history");

    await login("a");await open();
    assert.doesNotMatch(await text(),/Guest question|Guest catalog answer|Guest follow-up/);
    assert.equal(await page.locator(".support-chat-input-field").inputValue(),"");
    await send("A question");await settled();
    assert.match(await text(),/Account A catalog answer/);
    assert.equal(chats[2].auth,"Bearer token-a");
    assert.deepEqual(chats[2].data.history,[]);
    pass("Guest -> A clears transcript and draft; A sends bearer without client user identity");

    await send("A delayed");await readyA.promise;
    await logout();await open();
    assert.doesNotMatch(await text(),/A question|A delayed|Account A catalog answer/);
    assert.equal(await page.locator(".support-chat-input-field").isEnabled(),true);
    await login("b");await open();
    assert.doesNotMatch(await text(),/A question|A delayed|Guest question/);
    await send("B delayed");await readyB.promise;
    heldA.resolve();await page.waitForTimeout(150);
    assert.doesNotMatch(await text(),/A private delayed answer/);
    assert.equal(await page.locator(".support-chat-input-field").isDisabled(),true);
    heldB.resolve();await settled();
    assert.match(await text(),/B private answer/);
    assert.equal(chats[4].auth,"Bearer token-b");
    assert.deepEqual(chats[4].data.history,[]);
    pass("A -> guest -> B clears private chat; delayed A response cannot alter B history or loading");

    await send("B failed delayed");await readyFailure.promise;
    await logout();await open();
    failedB.resolve();await page.waitForTimeout(150);
    assert.doesNotMatch(await text(),/B private answer|B delayed|B failed delayed|couldn't connect/);
    assert.equal(await page.locator(".support-chat-input-field").isEnabled(),true);
    assert.equal(await page.locator(".support-chat-history [role=alert]").count(),0);
    await send("New guest question");await settled();
    assert.deepEqual(chats.at(-1).data.history,[]);
    assert.equal(chats.at(-1).auth,undefined);
    pass("Logout resets B history and ignores late rejection; new guest gets no private context");

    for (const chat of chats) {
      assert.match(chat.id,/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
      assert.deepEqual(Object.keys(chat.data).sort(),["history","message"]);
    }
    assert.equal(new Set(chats.map(chat=>chat.id)).size,chats.length);
    assert.deepEqual(errors,[]);
    pass("Each new send has a distinct UUID; no user/user_id payloads or browser runtime errors");
  } finally {
    heldA.resolve();heldB.resolve();failedB.resolve();
    await browser.close();
    await new Promise(resolve=>server.close(resolve));
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
