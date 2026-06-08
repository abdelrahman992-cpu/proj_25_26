from pydantic import BaseModel
from typing import Optional, List
from enum import Enum
from datetime import datetime


class TermSchema(BaseModel):
    term: str
    trans: str
    defe: str
    # اجعل user_id اختيارياً أو احذفه إذا كنت لا ترسل القيمة من الـ PHP
    user_id: Optional[int] = None

class UserCreate(BaseModel):
    username: str
    passwor: str  # تأكد من تطابق الاسم مع ما ترسله
    email: str    # تأكد أن هذا الحقل موجود هنا
    phone: str    # تأكد أن هذا الحقل موجود هنا

class UserUpdate(BaseModel):
    username: Optional[str] = None
    passwor: Optional[str] = None

class LoginSchema(BaseModel):
    username: str
    passwor: str

class StatusEnum(str, Enum):
    pending = "pending"
    approved = "approved"
    rejected = "rejected"

class SessionSchema(BaseModel): # تعبر عن جدول sections
    section_name: str
    user_id: int
    class Config: from_attributes = True

class UserSchema(BaseModel):
    id: int
    username: str
    class Config: from_attributes = True
class LoginSchema(BaseModel):
    username: str
    passwor: str  # مع الالتزام بالاسم الذي طلبته


